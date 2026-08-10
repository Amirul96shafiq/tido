<?php

declare(strict_types=1);

use App\Services\BackupService;
use Illuminate\Support\Facades\File;

/**
 * @return array{0: string, 1: string}
 */
function createBackupZipFixture(array $entries): array
{
    $directory = storage_path('app/backup-temp/'.uniqid('sec004_', true));
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

function extractBackupPayloadViaReflection(string $zipPath): string
{
    $method = new ReflectionMethod(BackupService::class, 'extractBackupPayloadFromZip');

    return $method->invoke(app(BackupService::class), $zipPath);
}

test('extracts native database.sqlite to a server-controlled path', function () {
    [$fixtureDirectory, $zipPath] = createBackupZipFixture([
        'database.sqlite' => 'sqlite-payload-bytes',
        'RESTORE_TOKEN.txt' => "token\n",
    ]);

    $payloadPath = null;

    try {
        $payloadPath = extractBackupPayloadViaReflection($zipPath);

        expect($payloadPath)->toEndWith(DIRECTORY_SEPARATOR.'database.sqlite')
            ->and(basename($payloadPath))->toBe('database.sqlite')
            ->and(File::get($payloadPath))->toBe('sqlite-payload-bytes')
            ->and(str_contains($payloadPath, 'backup-restore'.DIRECTORY_SEPARATOR))->toBeTrue();
    } finally {
        if (is_string($payloadPath) && File::isDirectory(dirname($payloadPath))) {
            File::deleteDirectory(dirname($payloadPath));
        }

        File::deleteDirectory($fixtureDirectory);
    }
});

test('extracts Spatie db-dumps sql entry to a server-controlled database.sql path', function () {
    [$fixtureDirectory, $zipPath] = createBackupZipFixture([
        'db-dumps/postgresql-example.sql' => 'SELECT 1;',
    ]);

    $payloadPath = null;

    try {
        $payloadPath = extractBackupPayloadViaReflection($zipPath);

        expect($payloadPath)->toEndWith(DIRECTORY_SEPARATOR.'database.sql')
            ->and(basename($payloadPath))->toBe('database.sql')
            ->and(File::get($payloadPath))->toBe('SELECT 1;');
    } finally {
        if (is_string($payloadPath) && File::isDirectory(dirname($payloadPath))) {
            File::deleteDirectory(dirname($payloadPath));
        }

        File::deleteDirectory($fixtureDirectory);
    }
});

test('prefers native database.sqlite over Spatie sql dumps', function () {
    [$fixtureDirectory, $zipPath] = createBackupZipFixture([
        'database.sqlite' => 'native-bytes',
        'db-dumps/postgresql-example.sql' => 'SELECT 1;',
    ]);

    $payloadPath = null;

    try {
        $payloadPath = extractBackupPayloadViaReflection($zipPath);

        expect(basename($payloadPath))->toBe('database.sqlite')
            ->and(File::get($payloadPath))->toBe('native-bytes');
    } finally {
        if (is_string($payloadPath) && File::isDirectory(dirname($payloadPath))) {
            File::deleteDirectory(dirname($payloadPath));
        }

        File::deleteDirectory($fixtureDirectory);
    }
});

test('rejects zip database entries with path traversal or unsafe separators', function (string $entryName) {
    [$fixtureDirectory, $zipPath] = createBackupZipFixture([
        $entryName => 'evil-bytes',
    ]);

    try {
        extractBackupPayloadViaReflection($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('No database dump found in backup archive.');
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
})->with([
    '../evil.sqlite',
    '..\\evil.sqlite',
    'db-dumps/../evil.sql',
    'db-dumps\\evil.sql',
    '/tmp/evil.sqlite',
    'C:/evil.sqlite',
]);

test('rejects non-allowlisted database-looking entries', function (string $entryName) {
    [$fixtureDirectory, $zipPath] = createBackupZipFixture([
        $entryName => 'unexpected-bytes',
    ]);

    try {
        extractBackupPayloadViaReflection($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('No database dump found in backup archive.');
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
})->with([
    'files/private/x.sqlite',
    'a/b/c.sqlite',
    'database.sql',
    'db-dumps/nested/path.sql',
    'db-dumps/evil.sql.gz',
]);

test('rejects archives without an allowlisted database payload', function () {
    [$fixtureDirectory, $zipPath] = createBackupZipFixture([
        'RESTORE_TOKEN.txt' => "token\n",
        'files/public/avatars/a.png' => 'png',
    ]);

    try {
        extractBackupPayloadViaReflection($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('No database dump found in backup archive.');
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});

test('rejects archives with multiple Spatie sql dumps', function () {
    [$fixtureDirectory, $zipPath] = createBackupZipFixture([
        'db-dumps/postgresql-one.sql' => 'SELECT 1;',
        'db-dumps/postgresql-two.sql' => 'SELECT 2;',
    ]);

    try {
        extractBackupPayloadViaReflection($zipPath);
        $this->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Multiple database dumps found in backup archive.');
    } finally {
        File::deleteDirectory($fixtureDirectory);
    }
});
