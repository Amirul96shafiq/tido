<?php

declare(strict_types=1);

use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use App\Support\BackupManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    config([
        'backup.backup.name' => 'tido',
        'backup.backup.destination.disks' => ['local'],
        'backup.backup.restore.max_upload_kilobytes' => 51200,
    ]);
});

/**
 * @param  array<string, string>  $entries
 * @return array{0: string, 1: string, 2: string, 3: string}
 */
function createSec006SignedZip(string $filename, array $entries): array
{
    $directory = storage_path('app/backup-temp/'.uniqid('sec006_', true));
    File::ensureDirectoryExists($directory);

    $zipPath = $directory.'/fixture.zip';
    $zip = new ZipArchive;

    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    expect($zip->open($zipPath))->toBeTrue();

    $contentSha256 = BackupManifest::contentSha256($zip);
    $canonicalJson = BackupManifest::encode($filename, $contentSha256);
    $hmac = BackupManifest::hmac($canonicalJson);

    $existingJson = $zip->locateName(BackupManifest::JSON_ENTRY);

    if ($existingJson !== false) {
        $zip->deleteIndex($existingJson);
    }

    $existingHmac = $zip->locateName(BackupManifest::HMAC_ENTRY);

    if ($existingHmac !== false) {
        $zip->deleteIndex($existingHmac);
    }

    $zip->addFromString(BackupManifest::JSON_ENTRY, $canonicalJson);
    $zip->addFromString(BackupManifest::HMAC_ENTRY, $hmac."\n");
    $zip->close();

    return [$directory, $zipPath, $contentSha256, $hmac];
}

function postSec006GuestRestore(string $zipPath, string $token): TestResponse
{
    return test()->postJson(route('restore-backup'), [
        'token' => $token,
        'backup' => new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true),
    ]);
}

test('matching token and signed archive restores and consumes the token', function () {
    expect(User::query()->exists())->toBeFalse();

    $filename = 'tido-app-local-sec006-success.zip';
    [$directory, $zipPath, $contentSha256, $hmac] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 1;',
    ]);

    $backup = Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/sec006-success.zip',
        'filename' => $filename,
        'created_by' => null,
        'content_sha256' => $contentSha256,
        'manifest_hmac' => $hmac,
    ]);

    try {
        postSec006GuestRestore($zipPath, 'valid-restore-token')
            ->assertSuccessful()
            ->assertJson([
                'success' => true,
                'message' => 'Backup restored. Please sign in.',
            ]);

        expect($backup->fresh()->restore_token_hash)->toBeNull();
    } finally {
        File::deleteDirectory($directory);
    }
});

test('matching token with a different archive is rejected before import', function () {
    expect(User::query()->exists())->toBeFalse();

    $filename = 'tido-app-local-sec006-mismatch.zip';
    [$genuineDirectory, $genuineZipPath, $contentSha256, $hmac] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 1;',
    ]);
    [$attackerDirectory, $attackerZipPath] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 2;',
    ]);

    $backup = Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/sec006-mismatch.zip',
        'filename' => $filename,
        'created_by' => null,
        'content_sha256' => $contentSha256,
        'manifest_hmac' => $hmac,
    ]);

    Storage::disk('local')->put($backup->path, File::get($genuineZipPath));

    try {
        postSec006GuestRestore($attackerZipPath, 'valid-restore-token')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid restore token or backup.',
            ]);

        expect($backup->fresh()->restore_token_hash)->not->toBeNull()
            ->and(User::query()->exists())->toBeFalse();
    } finally {
        File::deleteDirectory($genuineDirectory);
        File::deleteDirectory($attackerDirectory);
    }
});

test('forged missing or truncated hmac is rejected when the catalog stores a mac', function (string $tamper) {
    expect(User::query()->exists())->toBeFalse();

    $filename = 'tido-app-local-sec006-hmac.zip';
    [$directory, $zipPath, $contentSha256, $hmac] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 1;',
    ]);

    $backup = Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/sec006-hmac.zip',
        'filename' => $filename,
        'created_by' => null,
        'content_sha256' => $contentSha256,
        'manifest_hmac' => $hmac,
    ]);

    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    $hmacIndex = $zip->locateName(BackupManifest::HMAC_ENTRY);
    expect($hmacIndex)->not->toBeFalse();
    $zip->deleteIndex($hmacIndex);

    if ($tamper === 'forged') {
        $zip->addFromString(BackupManifest::HMAC_ENTRY, str_repeat('0', 64)."\n");
    } elseif ($tamper === 'truncated') {
        $zip->addFromString(BackupManifest::HMAC_ENTRY, "abcd\n");
    }

    $zip->close();

    try {
        postSec006GuestRestore($zipPath, 'valid-restore-token')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid restore token or backup.',
            ]);

        expect($backup->fresh()->restore_token_hash)->not->toBeNull();
    } finally {
        File::deleteDirectory($directory);
    }
})->with(['forged', 'missing', 'truncated']);

test('legacy catalog rows backfill identity from the stored file and reject mismatches', function () {
    expect(User::query()->exists())->toBeFalse();

    $filename = 'tido-app-local-sec006-legacy.zip';
    [$directory, $zipPath, $contentSha256] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 1;',
    ]);

    $backup = Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/sec006-legacy.zip',
        'filename' => $filename,
        'created_by' => null,
        'content_sha256' => null,
        'manifest_hmac' => null,
    ]);

    Storage::disk('local')->put($backup->path, File::get($zipPath));

    [$attackerDirectory, $attackerZipPath] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 9;',
    ]);

    try {
        postSec006GuestRestore($attackerZipPath, 'valid-restore-token')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid restore token or backup.',
            ]);

        $backup->refresh();

        expect($backup->content_sha256)->toBe($contentSha256)
            ->and($backup->restore_token_hash)->not->toBeNull();
    } finally {
        File::deleteDirectory($directory);
        File::deleteDirectory($attackerDirectory);
    }
});

test('legacy catalog rows without a stored file or hash fail closed', function () {
    expect(User::query()->exists())->toBeFalse();

    $filename = 'tido-app-local-sec006-missing.zip';
    [$directory, $zipPath] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 1;',
    ]);

    $backup = Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/sec006-missing.zip',
        'filename' => $filename,
        'created_by' => null,
        'content_sha256' => null,
        'manifest_hmac' => null,
    ]);

    try {
        postSec006GuestRestore($zipPath, 'valid-restore-token')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid restore token or backup.',
            ]);

        expect($backup->fresh()->restore_token_hash)->not->toBeNull()
            ->and($backup->fresh()->content_sha256)->toBeNull();
    } finally {
        File::deleteDirectory($directory);
    }
});

test('a second restore is rejected while the restore lock is held', function () {
    expect(User::query()->exists())->toBeFalse();

    $filename = 'tido-app-local-sec006-lock.zip';
    [$directory, $zipPath, $contentSha256, $hmac] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 1;',
    ]);

    Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/sec006-lock.zip',
        'filename' => $filename,
        'created_by' => null,
        'content_sha256' => $contentSha256,
        'manifest_hmac' => $hmac,
    ]);

    $lock = Cache::store('file')->lock('backup-restore', 90);

    expect($lock->get())->toBeTrue();

    try {
        postSec006GuestRestore($zipPath, 'valid-restore-token')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid restore token or backup.',
            ]);
    } finally {
        $lock->release();
        File::deleteDirectory($directory);
    }
});

test('failed import rolls back application files and keeps the restore token', function () {
    Storage::disk('public')->put('receipts/old.png', 'old-bytes');

    $filename = 'tido-app-local-sec006-rollback.zip';
    [$directory, $zipPath, $contentSha256, $hmac] = createSec006SignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 1;',
        'files/public/receipts/old.png' => 'new-bytes',
        'files/public/receipts/added.png' => 'added-bytes',
    ]);

    $backup = Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/sec006-rollback.zip',
        'filename' => $filename,
        'created_by' => null,
        'content_sha256' => $contentSha256,
        'manifest_hmac' => $hmac,
    ]);

    $service = Mockery::mock(BackupService::class)->makePartial();
    $service->shouldReceive('restoreFromZipPath')->once()->andReturnUsing(function (): void {
        Storage::disk('public')->put('receipts/old.png', 'new-bytes');
        Storage::disk('public')->put('receipts/added.png', 'added-bytes');

        throw new RuntimeException('forced import failure');
    });

    try {
        expect(fn () => $service->restoreGuestUpload($backup, $zipPath, 'valid-restore-token'))
            ->toThrow(RuntimeException::class, 'forced import failure');

        expect(Storage::disk('public')->get('receipts/old.png'))->toBe('old-bytes')
            ->and(Storage::disk('public')->exists('receipts/added.png'))->toBeFalse()
            ->and($backup->fresh()->restore_token_hash)->not->toBeNull();
    } finally {
        File::deleteDirectory($directory);
    }
});

test('failed sqlite import restores the previous database file', function () {
    $livePath = storage_path('app/backup-temp/'.uniqid('sec006_live_', true).'.sqlite');
    File::ensureDirectoryExists(dirname($livePath));

    $live = new PDO('sqlite:'.$livePath);
    $live->exec('CREATE TABLE restore_probe (id INTEGER PRIMARY KEY, name TEXT)');
    $live->exec("INSERT INTO restore_probe (name) VALUES ('original')");
    $live = null;

    config(['database.connections.sqlite.database' => $livePath]);

    $filename = 'tido-app-local-sec006-sqlite-rollback.zip';
    [$directory, $zipPath] = createSec006SignedZip($filename, [
        'database.sqlite' => 'imported-bytes',
    ]);

    $createSnapshot = new ReflectionMethod(BackupService::class, 'createRestoreSnapshot');
    $deleteSnapshot = new ReflectionMethod(BackupService::class, 'deleteRestoreSnapshot');

    $service = app(BackupService::class);
    $snapshot = $createSnapshot->invoke($service, $zipPath);

    File::put($livePath, 'imported-bytes');

    try {
        expect(File::get($livePath))->toBe('imported-bytes')
            ->and(is_string($snapshot['sqlite']) && File::exists($snapshot['sqlite']))->toBeTrue();

        File::copy($snapshot['sqlite'], $livePath);

        $probe = new PDO('sqlite:'.$livePath);
        $name = $probe->query('SELECT name FROM restore_probe')->fetchColumn();
        $probe = null;

        expect($name)->toBe('original');
    } finally {
        $deleteSnapshot->invoke($service, $snapshot);
        File::delete($livePath);
        File::deleteDirectory($directory);
        config(['database.connections.sqlite.database' => ':memory:']);
    }
});

test('issueRestoreToken does not change content identity and embeds a signed manifest', function () {
    $zipPath = storage_path('app/backup-temp/'.uniqid('sec006_issue_', true).'.zip');
    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('database.sqlite', 'sqlite-bytes');
    $zip->close();

    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'tido/sec006-issue.zip',
        'filename' => 'tido-app-local-sec006-issue.zip',
        'restore_token_hash' => null,
        'content_sha256' => null,
        'manifest_hmac' => null,
        'created_by' => null,
    ]);

    Storage::disk('local')->put($backup->path, File::get($zipPath));

    $firstToken = app(BackupService::class)->issueRestoreToken($backup);
    $backup->refresh();

    $firstHash = $backup->content_sha256;
    $firstHmac = $backup->manifest_hmac;

    expect($firstHash)->toBeString()->toHaveLength(64)
        ->and($firstHmac)->toBeString()->toHaveLength(64)
        ->and(Str::isMatch('/^[a-f0-9]{64}$/', $firstHash))->toBeTrue()
        ->and(Str::isMatch('/^[a-f0-9]{64}$/', $firstHmac))->toBeTrue();

    $storedZip = storage_path('app/backup-temp/'.uniqid('sec006_issued_', true).'.zip');
    File::put($storedZip, Storage::disk('local')->get($backup->path));

    $assertZip = new ZipArchive;
    $assertZip->open($storedZip);
    $json = $assertZip->getFromName(BackupManifest::JSON_ENTRY);
    $hmacEntry = $assertZip->getFromName(BackupManifest::HMAC_ENTRY);
    $assertZip->close();

    expect($json)->toBeString()
        ->and($hmacEntry)->toBeString()
        ->and(BackupManifest::hmacIsValid($json, trim($hmacEntry)))->toBeTrue();

    $decoded = BackupManifest::decode($json);

    expect($decoded)->not->toBeNull()
        ->and($decoded['content_sha256'])->toBe($firstHash)
        ->and($decoded['filename'])->toBe($backup->filename);

    app(BackupService::class)->issueRestoreToken($backup->fresh());
    $backup->refresh();

    expect($backup->content_sha256)->toBe($firstHash)
        ->and($backup->manifest_hmac)->toBe($firstHmac)
        ->and(app(BackupService::class)->assertRestoreToken($backup, $firstToken))->toBeFalse();

    File::delete($zipPath);
    File::delete($storedZip);
});
