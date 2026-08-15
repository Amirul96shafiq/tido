<?php

declare(strict_types=1);

use App\Services\BackupService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: string, 1: string}
 */
function createSec005ZipFixture(array $entries): array
{
    $directory = storage_path('app/backup-temp/'.uniqid('sec005_', true));
    File::ensureDirectoryExists($directory);

    $zipPath = $directory.'/fixture.zip';
    $zip = new ZipArchive;

    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return [$directory, $zipPath];
}

function inspectSec005ZipViaReflection(string $zipPath): void
{
    $method = new ReflectionMethod(BackupService::class, 'inspectZipResourceLimits');
    $method->invoke(app(BackupService::class), $zipPath);
}

function restoreSec005ApplicationFilesViaReflection(string $zipPath): void
{
    $method = new ReflectionMethod(BackupService::class, 'restoreApplicationFilesFromZip');
    $method->invoke(app(BackupService::class), $zipPath);
}

/**
 * @return list<string>
 */
function backupRestorePayloadDirectories(): array
{
    $root = storage_path('app/backup-restore');

    if (! File::isDirectory($root)) {
        return [];
    }

    return File::directories($root);
}

test('inspects a legitimate native backup zip without writing payload files', function () {
    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => 'sqlite-bytes',
        'files/public/avatars/x.png' => 'png-bytes',
    ]);

    $payloadDirectoriesBefore = backupRestorePayloadDirectories();

    try {
        inspectSec005ZipViaReflection($zipPath);

        expect(backupRestorePayloadDirectories())->toBe($payloadDirectoriesBefore);
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('rejects archives that exceed the entry count before any payload write', function () {
    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => 'sqlite-bytes',
        'extra-one.txt' => 'a',
        'extra-two.txt' => 'b',
    ]);

    config(['backup.backup.restore.max_entries' => 2]);

    $payloadDirectoriesBefore = backupRestorePayloadDirectories();

    try {
        inspectSec005ZipViaReflection($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Backup archive exceeds restore limits.')
            ->and(backupRestorePayloadDirectories())->toBe($payloadDirectoriesBefore);
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('rejects archives with an entry larger than max_entry_bytes', function () {
    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => '12345',
    ]);

    config(['backup.backup.restore.max_entry_bytes' => 4]);

    try {
        inspectSec005ZipViaReflection($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Backup archive exceeds restore limits.');
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('rejects archives whose uncompressed total exceeds max_uncompressed_bytes', function () {
    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => '1234',
        'files/public/avatars/x.png' => '5678',
    ]);

    config(['backup.backup.restore.max_uncompressed_bytes' => 6]);

    try {
        inspectSec005ZipViaReflection($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Backup archive exceeds restore limits.');
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('rejects archives whose compression ratio exceeds the configured maximum', function () {
    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => str_repeat('A', 10000),
    ]);

    config(['backup.backup.restore.max_compression_ratio' => 2]);

    try {
        inspectSec005ZipViaReflection($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Backup archive exceeds restore limits.');
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('restoreFromZipPath does not extract or import when inspect fails', function () {
    Storage::fake('public');
    Storage::fake('local');

    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => 'sqlite-bytes-should-not-import',
        'files/public/avatars/x.png' => 'png-bytes',
        'extra.txt' => 'x',
    ]);

    config(['backup.backup.restore.max_entries' => 1]);

    $payloadDirectoriesBefore = backupRestorePayloadDirectories();

    try {
        app(BackupService::class)->restoreFromZipPath($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Backup archive exceeds restore limits.')
            ->and(backupRestorePayloadDirectories())->toBe($payloadDirectoriesBefore)
            ->and(Storage::disk('public')->exists('avatars/x.png'))->toBeFalse();
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('restoreFromZipPath rejects archives when the duration limit is already elapsed', function () {
    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => 'sqlite-bytes',
    ]);

    config(['backup.backup.restore.max_duration_seconds' => 0]);

    $payloadDirectoriesBefore = backupRestorePayloadDirectories();

    try {
        app(BackupService::class)->restoreFromZipPath($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Backup archive exceeds restore limits.')
            ->and(backupRestorePayloadDirectories())->toBe($payloadDirectoriesBefore);
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('skips traversal and disallowed application-file extensions without writing them', function () {
    Storage::fake('public');
    Storage::fake('local');

    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => 'sqlite-bytes',
        'files/public/avatars/ok.png' => 'png-bytes',
        'files/public/evil.php' => '<?php echo 1;',
        'files/public/../escaped.png' => 'escaped-bytes',
        'files/private/receipts/ok.pdf' => 'pdf-bytes',
        'app/Http/Kernel.php' => 'spatie-source',
    ]);

    try {
        restoreSec005ApplicationFilesViaReflection($zipPath);

        expect(Storage::disk('public')->get('avatars/ok.png'))->toBe('png-bytes')
            ->and(Storage::disk('public')->exists('evil.php'))->toBeFalse()
            ->and(Storage::disk('public')->exists('escaped.png'))->toBeFalse()
            ->and(Storage::disk('local')->get('receipts/ok.pdf'))->toBe('pdf-bytes')
            ->and(Storage::disk('local')->exists('app/Http/Kernel.php'))->toBeFalse()
            ->and(Storage::disk('public')->exists('app/Http/Kernel.php'))->toBeFalse();
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('inspect allows extra Spatie source entries that are not restored as application files', function () {
    [$fixtureDirectory, $zipPath] = createSec005ZipFixture([
        'database.sqlite' => 'sqlite-bytes',
        'app/Models/User.php' => 'class User {}',
        'vendor/autoload.php' => '<?php',
    ]);

    try {
        inspectSec005ZipViaReflection($zipPath);
        expect(true)->toBeTrue();
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});
