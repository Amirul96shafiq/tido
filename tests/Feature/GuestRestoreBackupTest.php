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

test('backup restore token is stored hashed and omitted from the zip', function () {
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
        ->and($backup->fresh()->restore_token_lookup)->not->toBeNull()
        ->and(app(BackupService::class)->assertRestoreToken($backup->fresh(), $plainToken))->toBeTrue();

    $storedZip = storage_path('app/backup-temp/token-assert.zip');
    File::put($storedZip, Storage::disk('local')->get($backup->path));

    $assertZip = new ZipArchive;
    $assertZip->open($storedZip);

    expect($assertZip->locateName('RESTORE_TOKEN.txt'))->toBeFalse();
    $assertZip->close();

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

    $this->getJson(route('backups.download', $backup))
        ->assertForbidden();
});

test('login page hides restore backup menu when users exist', function () {
    User::factory()->create();

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Changelogs 🡥')
        ->assertDontSee('showRestoreBackupModal')
        ->assertDontSee('data-fi-modal-id="restore-backup"', false);
});

test('login page shows restore backup menu when no users exist', function () {
    expect(User::query()->exists())->toBeFalse();

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Changelogs 🡥')
        ->assertSee('Restore Backup 🡥')
        ->assertSee('showRestoreBackupModal', false)
        ->assertSee('data-fi-modal-id="restore-backup"', false)
        ->assertSee('id="restore-backup-file"', false)
        ->assertSee('id="restore-backup-token"', false)
        ->assertSee('id="restore-backup-feedback"', false)
        ->assertSee('fi-fo-field', false)
        ->assertSee('fi-fo-field-wrp-error-message', false)
        ->assertSee('fi-input-wrp', false)
        ->assertSee('fi-modal-footer-actions', false)
        ->assertSee('Restore backup')
        ->assertSee('this.feedbackMessage = message', false)
        ->assertDontSee('auth-toast')
        ->assertDontSee('x-on:auth-toast.window', false);
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

    Backup::factory()->withRestoreToken('1122334455667788.aabbccddeeff00112233445566778899')->create([
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
        'token' => '9988776655443322.ffeeddccbbaa00998877665544332211',
        'backup' => new UploadedFile($zipPath, 'wrong-token.zip', 'application/zip', null, true),
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid restore token or backup.',
        ]);

    File::delete($zipPath);
});

test('guest restore stages valid uploads under a server-controlled path', function () {
    expect(User::query()->exists())->toBeFalse();

    $backup = Backup::factory()->withRestoreToken('aabbccddeeff0011.11223344556677889900aabbccddeeff')->create([
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
    $zip->close();

    $this->mock(BackupService::class, function ($mock) use ($backup): void {
        $mock->shouldReceive('findBackupByRestoreToken')
            ->once()
            ->with('aabbccddeeff0011.11223344556677889900aabbccddeeff')
            ->andReturn($backup);

        $mock->shouldReceive('restoreGuestUpload')
            ->once()
            ->withArgs(function (Backup $record, string $path, string $token) use ($backup): bool {
                $restoreRoot = realpath(storage_path('app/backup-restore'));
                $stagingDirectory = realpath(dirname($path));

                if ($restoreRoot === false || $stagingDirectory === false) {
                    return false;
                }

                $restoreRoot = rtrim($restoreRoot, '/\\').DIRECTORY_SEPARATOR;

                if (DIRECTORY_SEPARATOR === '\\') {
                    $restoreRoot = strtolower($restoreRoot);
                    $stagingDirectory = strtolower($stagingDirectory);
                }

                return $record->is($backup)
                    && $token === 'aabbccddeeff0011.11223344556677889900aabbccddeeff'
                    && basename($path) === 'backup.zip'
                    && str_starts_with($stagingDirectory, $restoreRoot);
            });
    });

    $response = $this->postJson(route('restore-backup'), [
        'token' => 'aabbccddeeff0011.11223344556677889900aabbccddeeff',
        'backup' => new UploadedFile($zipPath, '..\\..\\CON.zip', 'application/zip', null, true),
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Backup restored. Please sign in.',
        ]);

    File::delete($zipPath);
});
