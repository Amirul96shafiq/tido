<?php

declare(strict_types=1);

use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use App\Support\BackupArchivePassword;
use App\Support\BackupManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
function createEncryptedSignedZip(string $filename, array $entries): array
{
    $password = BackupArchivePassword::require();
    $directory = storage_path('app/backup-temp/'.uniqid('sec007_', true));
    File::ensureDirectoryExists($directory);

    $zipPath = $directory.'/fixture.zip';
    $zip = new ZipArchive;

    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
        expect($zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password))->toBeTrue();
    }

    $zip->close();

    expect($zip->open($zipPath))->toBeTrue();
    $zip->setPassword($password);

    $contentSha256 = BackupManifest::contentSha256($zip);
    $canonicalJson = BackupManifest::encode($filename, $contentSha256);
    $hmac = BackupManifest::hmac($canonicalJson);

    $zip->addFromString(BackupManifest::JSON_ENTRY, $canonicalJson);
    expect($zip->setEncryptionName(BackupManifest::JSON_ENTRY, ZipArchive::EM_AES_256, $password))->toBeTrue();
    $zip->addFromString(BackupManifest::HMAC_ENTRY, $hmac."\n");
    expect($zip->setEncryptionName(BackupManifest::HMAC_ENTRY, ZipArchive::EM_AES_256, $password))->toBeTrue();
    $zip->close();

    return [$directory, $zipPath, $contentSha256, $hmac];
}

test('archive password rejects missing short and placeholder values', function (mixed $password) {
    config(['backup.backup.password' => $password]);

    expect(BackupArchivePassword::isValid($password))->toBeFalse()
        ->and(fn () => BackupArchivePassword::require())
        ->toThrow(RuntimeException::class, BackupArchivePassword::UNAVAILABLE_MESSAGE);
})->with([
    'null' => [null],
    'empty' => [''],
    'short' => ['too-short-to-be-valid-key'],
    'placeholder' => ['<backup-archive-password-32-chars-minimum>'],
    'named-placeholder' => ['your-archive-password'],
]);

test('create fails closed when the archive password is missing', function () {
    config(['backup.backup.password' => null]);

    expect(fn () => app(BackupService::class)->create(BackupType::Manual, User::factory()->create()))
        ->toThrow(RuntimeException::class, BackupArchivePassword::UNAVAILABLE_MESSAGE);
});

test('restore fails closed when the archive password is missing', function () {
    $zipPath = storage_path('app/backup-temp/'.uniqid('sec007_restore_', true).'.zip');
    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('database.sqlite', 'sqlite-bytes');
    $zip->close();

    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'tido/sec007-restore.zip',
        'filename' => 'sec007-restore.zip',
    ]);

    Storage::disk('local')->put($backup->path, File::get($zipPath));
    config(['backup.backup.password' => null]);

    expect(fn () => app(BackupService::class)->restore($backup))
        ->toThrow(RuntimeException::class, BackupArchivePassword::UNAVAILABLE_MESSAGE);

    File::delete($zipPath);
});

test('spatie backup source excludes env and does not include the project root', function () {
    expect(config('backup.backup.source.files.include'))->toBe([])
        ->and(config('backup.backup.source.files.exclude'))->toContain(base_path('.env'))
        ->and(config('backup.backup.source.files.exclude'))->toContain(base_path('.env.sandbox'))
        ->and(config('backup.backup.encryption'))->toBe(ZipArchive::EM_AES_256);
});

test('native backup zip is encrypted without a restore token file', function () {
    $databasePath = database_path('testing-sec007-backup.sqlite');

    if (File::exists($databasePath)) {
        File::delete($databasePath);
    }

    if (File::exists(database_path('database.sqlite'))) {
        File::copy(database_path('database.sqlite'), $databasePath);
    } else {
        File::put($databasePath, '');
    }

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $databasePath,
    ]);

    $user = User::factory()->create();
    Event::fake([MessageLogged::class]);

    $created = app(BackupService::class)->create(BackupType::Manual, $user);
    $backup = $created->backup;

    expect($created->restoreToken)->toHaveLength(32)
        ->and($backup->restore_token_hash)->not->toBeNull()
        ->and($backup->fileExists())->toBeTrue();

    $storedZip = storage_path('app/backup-temp/'.uniqid('sec007_', true).'.zip');
    File::ensureDirectoryExists(dirname($storedZip));
    File::put($storedZip, Storage::disk('local')->get($backup->path));

    $zip = new ZipArchive;
    expect($zip->open($storedZip))->toBeTrue();
    expect($zip->locateName('RESTORE_TOKEN.txt'))->toBeFalse();

    $withoutPassword = $zip->getFromName('database.sqlite');
    expect($withoutPassword === false || $withoutPassword === '')->toBeTrue();

    $zip->setPassword($created->restoreToken);
    $withRestoreToken = $zip->getFromName('database.sqlite');
    expect($withRestoreToken === false || $withRestoreToken === '')->toBeTrue();

    $zip->setPassword(BackupArchivePassword::require());
    $withPassword = $zip->getFromName('database.sqlite');
    expect($withPassword)->not->toBeFalse()
        ->and($withPassword)->not->toBe('');
    $zip->close();

    Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($created, $backup): bool {
        if ($event->level !== 'info' || $event->message !== 'backup.created') {
            return false;
        }

        $encoded = json_encode($event->context);

        return ($event->context['backup_id'] ?? null) === $backup->getKey()
            && ($event->context['outcome'] ?? null) === 'created'
            && is_string($encoded)
            && ! str_contains($encoded, $created->restoreToken)
            && ! array_key_exists('token', $event->context)
            && ! array_key_exists('password', $event->context);
    });

    File::delete($storedZip);
    File::delete($databasePath);
})->skip(fn (): bool => ! defined('ZipArchive::EM_AES_256'), 'ZipArchive AES-256 is unavailable.');

test('guest restore succeeds with a catalog token and an encrypted zip', function () {
    expect(User::query()->exists())->toBeFalse();

    $filename = 'tido-app-local-sec007-guest.zip';
    [$directory, $zipPath, $contentSha256, $hmac] = createEncryptedSignedZip($filename, [
        'db-dumps/sqlite.sql' => 'SELECT 1;',
    ]);

    $backup = Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/sec007-guest.zip',
        'filename' => $filename,
        'created_by' => null,
        'content_sha256' => $contentSha256,
        'manifest_hmac' => $hmac,
    ]);

    $probe = new ZipArchive;
    expect($probe->open($zipPath))->toBeTrue();
    expect($probe->locateName('RESTORE_TOKEN.txt'))->toBeFalse();
    $plaintext = $probe->getFromName('db-dumps/sqlite.sql');
    expect($plaintext === false || $plaintext === '')->toBeTrue();
    $probe->setPassword('valid-restore-token');
    $tokenAsPassword = $probe->getFromName('db-dumps/sqlite.sql');
    expect($tokenAsPassword === false || $tokenAsPassword === '')->toBeTrue();
    $probe->close();

    try {
        $this->postJson(route('restore-backup'), [
            'token' => 'valid-restore-token',
            'backup' => new UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true),
        ])
            ->assertSuccessful()
            ->assertJson([
                'success' => true,
                'message' => 'Backup restored. Please sign in.',
            ]);

        expect($backup->fresh()->restore_token_hash)->toBeNull();
    } finally {
        File::deleteDirectory($directory);
    }
})->skip(fn (): bool => ! defined('ZipArchive::EM_AES_256'), 'ZipArchive AES-256 is unavailable.');

test('signed backup download logs without secrets', function () {
    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'tido/sec007-download.zip',
        'filename' => 'sec007-download.zip',
    ]);

    Storage::disk('local')->put($backup->path, 'backup-bytes');

    Event::fake([MessageLogged::class]);

    $signedUrl = URL::temporarySignedRoute(
        'backups.download',
        now()->addMinutes(10),
        ['backup' => $backup],
    );

    $this->get($signedUrl)->assertSuccessful();

    Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($backup): bool {
        if ($event->level !== 'info' || $event->message !== 'backup.downloaded') {
            return false;
        }

        $encoded = json_encode($event->context);

        return ($event->context['backup_id'] ?? null) === $backup->getKey()
            && ($event->context['outcome'] ?? null) === 'downloaded'
            && is_string($encoded)
            && ! array_key_exists('token', $event->context)
            && ! array_key_exists('password', $event->context)
            && ! str_contains($encoded, 'receipts/');
    });
});
