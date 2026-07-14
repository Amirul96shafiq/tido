<?php

declare(strict_types=1);

use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config([
        'backup.backup.name' => 'tido',
        'backup.backup.destination.disks' => ['local'],
        'backup.backup.restore.max_upload_kilobytes' => 51200,
    ]);
});

test('backup restore token is embedded in zip and stored hashed', function () {
    $zipPath = storage_path('app/backup-temp/token-source.zip');
    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('database.sqlite', 'sqlite-bytes');
    $zip->close();

    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'tido/token-source.zip',
        'filename' => 'token-source.zip',
        'restore_token_hash' => null,
    ]);

    Storage::disk('local')->put($backup->path, File::get($zipPath));

    $plainToken = app(BackupService::class)->issueRestoreToken($backup);

    expect($backup->fresh()->restore_token_hash)->not->toBeNull()
        ->and(app(BackupService::class)->assertRestoreToken($backup->fresh(), $plainToken))->toBeTrue();

    $storedZip = storage_path('app/backup-temp/token-assert.zip');
    File::put($storedZip, Storage::disk('local')->get($backup->path));

    $assertZip = new ZipArchive;
    $assertZip->open($storedZip);
    $embedded = trim((string) $assertZip->getFromName('RESTORE_TOKEN.txt'));
    $assertZip->close();

    expect($embedded)->toBe($plainToken);

    File::delete($zipPath);
    File::delete($storedZip);
});

test('signed backup download works without auth and rejects unsigned urls', function () {
    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'tido/signed-download.zip',
        'filename' => 'signed-download.zip',
    ]);

    Storage::disk('local')->put($backup->path, 'backup-bytes');

    $signedUrl = URL::temporarySignedRoute(
        'backups.download',
        now()->addMinutes(10),
        ['backup' => $backup],
    );

    $this->get($signedUrl)
        ->assertSuccessful();

    $this->get(route('backups.download', $backup))
        ->assertForbidden();
});

test('login page hides restore backup menu when users exist', function () {
    User::factory()->create();

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Changelogs')
        ->assertDontSee('showRestoreBackupModal')
        ->assertDontSee('open-restore-backup-modal');
});

test('login page shows restore backup menu when no users exist', function () {
    expect(User::query()->exists())->toBeFalse();

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Changelogs')
        ->assertSee('Restore Backup')
        ->assertSee('showRestoreBackupModal', false);
});

test('guest restore rejects requests when users still exist', function () {
    User::factory()->create();

    $response = $this->postJson(route('restore-backup'), [
        'token' => 'some-token-value',
        'backup' => UploadedFile::fake()->create('backup.zip', 100, 'application/zip'),
    ]);

    $response->assertForbidden();
});

test('guest restore rejects non zip uploads', function () {
    $response = $this->postJson(route('restore-backup'), [
        'token' => 'some-token-value',
        'backup' => UploadedFile::fake()->create('backup.txt', 100, 'text/plain'),
    ]);

    $response->assertStatus(422);
});

test('guest restore rejects wrong token', function () {
    expect(User::query()->exists())->toBeFalse();

    Backup::factory()->withRestoreToken('correct-token-value')->create([
        'disk' => 'local',
        'path' => 'tido/wrong-token.zip',
        'filename' => 'wrong-token.zip',
        'created_by' => null,
    ]);

    $zipPath = storage_path('app/backup-temp/guest-wrong-token.zip');
    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('database.sqlite', 'sqlite');
    $zip->close();

    $response = $this->postJson(route('restore-backup'), [
        'token' => 'incorrect-token-value',
        'backup' => new UploadedFile($zipPath, 'wrong-token.zip', 'application/zip', null, true),
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid restore token or backup.',
        ]);

    File::delete($zipPath);
});

test('guest restore succeeds with valid token and zip payload', function () {
    expect(User::query()->exists())->toBeFalse();

    $backup = Backup::factory()->withRestoreToken('valid-restore-token')->create([
        'disk' => 'local',
        'path' => 'tido/valid-restore.zip',
        'filename' => 'valid-restore.zip',
        'created_by' => null,
    ]);

    $zipPath = storage_path('app/backup-temp/guest-valid-restore.zip');
    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('database.sqlite', 'sqlite');
    $zip->addFromString('RESTORE_TOKEN.txt', "valid-restore-token\n");
    $zip->close();

    $this->mock(BackupService::class, function ($mock) use ($backup): void {
        $mock->shouldReceive('findBackupByRestoreToken')
            ->once()
            ->with('valid-restore-token')
            ->andReturn($backup);

        $mock->shouldReceive('restoreFromZipPath')
            ->once()
            ->withArgs(fn (string $path): bool => str_ends_with($path, 'valid-restore.zip'));

        $mock->shouldReceive('consumeRestoreToken')
            ->once()
            ->withArgs(fn (Backup $record): bool => $record->is($backup));
    });

    $response = $this->postJson(route('restore-backup'), [
        'token' => 'valid-restore-token',
        'backup' => new UploadedFile($zipPath, 'valid-restore.zip', 'application/zip', null, true),
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Backup restored. Please sign in.',
        ]);

    File::delete($zipPath);
});
