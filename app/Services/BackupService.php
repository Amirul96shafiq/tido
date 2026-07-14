<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BackupService
{
    private static bool $skipScheduledCatalogRegistration = false;

    public static function skipScheduledCatalogRegistration(): void
    {
        self::$skipScheduledCatalogRegistration = true;
    }

    public static function shouldRegisterScheduledCatalog(): bool
    {
        return ! self::$skipScheduledCatalogRegistration;
    }

    public static function resetScheduledCatalogFlag(): void
    {
        self::$skipScheduledCatalogRegistration = false;
    }

    public function create(BackupType $type, ?User $createdBy = null): Backup
    {
        self::skipScheduledCatalogRegistration();

        try {
            if ($this->shouldUseNativeDatabaseBackup()) {
                return $this->createNativeDatabaseBackup($type, $createdBy);
            }

            return $this->createSpatieBackup($type, $createdBy);
        } finally {
            self::resetScheduledCatalogFlag();
        }
    }

    public function registerFromScheduledBackup(BackupDestination $destination): ?Backup
    {
        $newestBackup = $destination->newestBackup();

        if ($newestBackup === null) {
            return null;
        }

        $diskName = $destination->diskName();
        $disk = Storage::disk($diskName);
        $oldPath = $newestBackup->path();
        $filename = $this->buildBackupFilename(BackupType::Auto, null);
        $newPath = $destination->backupName().'/'.$filename;

        if ($oldPath !== $newPath && $disk->exists($oldPath)) {
            $disk->move($oldPath, $newPath);
        } elseif ($disk->exists($oldPath)) {
            $newPath = $oldPath;
            $filename = basename($oldPath);
        } else {
            return null;
        }

        if (Backup::query()->where('disk', $diskName)->where('path', $newPath)->exists()) {
            return null;
        }

        $plainToken = $this->generateRestoreToken();
        $this->embedApplicationFilesOnDisk($diskName, $newPath);
        $this->embedRestoreTokenOnDisk($diskName, $newPath, $plainToken);

        return Backup::query()->create([
            'type' => BackupType::Auto,
            'disk' => $diskName,
            'path' => $newPath,
            'filename' => $filename,
            'size_bytes' => $disk->exists($newPath) ? $disk->size($newPath) : null,
            'created_by' => null,
            'restore_token_hash' => Hash::make($plainToken),
        ]);
    }

    public function restore(Backup $backup): void
    {
        if (! $backup->fileExists()) {
            throw new RuntimeException('Backup file is missing from storage.');
        }

        $tempDirectory = storage_path('app/backup-restore/'.uniqid('restore_', true));
        File::ensureDirectoryExists($tempDirectory);

        $zipPath = $tempDirectory.'/backup.zip';
        File::put($zipPath, Storage::disk($backup->disk)->get($backup->path));

        try {
            $this->restoreFromZipPath($zipPath);
        } finally {
            if (File::isDirectory($tempDirectory)) {
                File::deleteDirectory($tempDirectory);
            }
        }
    }

    public function restoreFromZipPath(string $zipPath): void
    {
        if (! File::exists($zipPath)) {
            throw new RuntimeException('Backup archive was not found.');
        }

        $payloadPath = $this->extractBackupPayloadFromZip($zipPath);

        try {
            if (str_ends_with($payloadPath, '.sql')) {
                $this->importSqlDump($payloadPath);
            } elseif (str_ends_with($payloadPath, '.sqlite')) {
                $this->importSqliteFile($payloadPath);
            } else {
                throw new RuntimeException('Unsupported backup payload format.');
            }

            $this->restoreApplicationFilesFromZip($zipPath);
            $this->flushCaches();
        } finally {
            if (File::isDirectory(dirname($payloadPath))) {
                File::deleteDirectory(dirname($payloadPath));
            }
        }
    }

    public function generateRestoreToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function assertRestoreToken(Backup $backup, string $plainToken): bool
    {
        return filled($backup->restore_token_hash)
            && Hash::check($plainToken, $backup->restore_token_hash);
    }

    public function consumeRestoreToken(Backup $backup): void
    {
        $backup->forceFill(['restore_token_hash' => null])->save();
    }

    public function issueRestoreToken(Backup $backup): string
    {
        if (! $backup->fileExists()) {
            throw new RuntimeException('Backup file is missing from storage.');
        }

        $plainToken = $this->generateRestoreToken();
        $this->embedRestoreTokenOnDisk($backup->disk, $backup->path, $plainToken);

        $backup->forceFill([
            'restore_token_hash' => Hash::make($plainToken),
            'size_bytes' => Storage::disk($backup->disk)->size($backup->path),
        ])->save();

        return $plainToken;
    }

    public function findBackupByRestoreToken(string $plainToken): ?Backup
    {
        return Backup::query()
            ->whereNotNull('restore_token_hash')
            ->get()
            ->first(fn (Backup $backup): bool => $this->assertRestoreToken($backup, $plainToken));
    }

    public function delete(Backup $backup): void
    {
        if ($backup->fileExists()) {
            Storage::disk($backup->disk)->delete($backup->path);
        }

        $backup->delete();
    }

    public function buildBackupFilename(BackupType $type, ?User $createdBy = null): string
    {
        $timezone = $createdBy?->preferredTimezone() ?? (string) config('app.timezone', 'UTC');
        $timestamp = now()->timezone($timezone)->format('Y-m-d-His');

        return sprintf(
            '%s-%s-%s-%s.zip',
            $this->backupApplicationSlug(),
            $this->backupEnvironmentSlug(),
            $timestamp,
            $type->value,
        );
    }

    protected function backupApplicationSlug(): string
    {
        $name = Str::slug((string) config('app.name', 'tido'));

        return $name.'-app';
    }

    protected function backupEnvironmentSlug(): string
    {
        return match ((string) config('app.env')) {
            'production', 'prod' => 'prod',
            'staging', 'stg' => 'stg',
            default => 'local',
        };
    }

    public function downloadResponse(Backup $backup): StreamedResponse
    {
        if (! $backup->fileExists()) {
            throw new RuntimeException('Backup file is missing from storage.');
        }

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename);
    }

    protected function shouldUseNativeDatabaseBackup(): bool
    {
        $connection = (string) config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            return false;
        }

        $database = config("database.connections.{$connection}.database");

        return is_string($database) && $database !== ':memory:';
    }

    protected function createSpatieBackup(BackupType $type, ?User $createdBy): Backup
    {
        $filename = $this->buildBackupFilename($type, $createdBy);

        $exitCode = Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
            '--filename' => $filename,
        ]);

        if ($exitCode !== 0) {
            $output = trim(Artisan::output());

            throw new RuntimeException(
                $output !== '' ? $output : 'Backup command failed.',
            );
        }

        return $this->storeBackupCatalogRecord($type, $createdBy, $filename);
    }

    protected function createNativeDatabaseBackup(BackupType $type, ?User $createdBy): Backup
    {
        $connection = (string) config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        return match ($driver) {
            'sqlite' => $this->createSqliteFileBackup($type, $createdBy, $connection),
            default => throw new RuntimeException("Native backup is not supported for driver [{$driver}]."),
        };
    }

    protected function createSqliteFileBackup(BackupType $type, ?User $createdBy, string $connection): Backup
    {
        $databasePath = config("database.connections.{$connection}.database");

        if (! is_string($databasePath) || ! File::exists($databasePath)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        $diskName = $this->backupDiskName();
        $backupName = $this->backupApplicationName();
        $filename = $this->buildBackupFilename($type, $createdBy);
        $relativePath = $backupName.'/'.$filename;
        $tempDirectory = storage_path('app/backup-temp/'.uniqid('backup_', true));

        File::ensureDirectoryExists($tempDirectory);

        $zipPath = $tempDirectory.'/'.$filename;

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create backup archive.');
        }

        $zip->addFile($databasePath, 'database.sqlite');
        $plainToken = $this->generateRestoreToken();
        $zip->addFromString('RESTORE_TOKEN.txt', $plainToken."\n");
        $zip->close();

        Storage::disk($diskName)->put($relativePath, File::get($zipPath));
        File::deleteDirectory($tempDirectory);

        return $this->storeBackupCatalogRecord($type, $createdBy, $filename, $plainToken);
    }

    /**
     * @param  non-empty-string|null  $plainToken
     */
    protected function storeBackupCatalogRecord(
        BackupType $type,
        ?User $createdBy,
        string $filename,
        ?string $plainToken = null,
    ): Backup {
        $diskName = $this->backupDiskName();
        $path = $this->backupApplicationName().'/'.$filename;
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            throw new RuntimeException('No backup file was created.');
        }

        $this->embedApplicationFilesOnDisk($diskName, $path);

        $plainToken ??= $this->generateRestoreToken();
        $this->embedRestoreTokenOnDisk($diskName, $path, $plainToken);

        return Backup::query()->create([
            'type' => $type,
            'disk' => $diskName,
            'path' => $path,
            'filename' => $filename,
            'size_bytes' => $disk->size($path),
            'created_by' => $createdBy?->getKey(),
            'restore_token_hash' => Hash::make($plainToken),
        ]);
    }

    protected function embedApplicationFilesOnDisk(string $diskName, string $path): void
    {
        $tempDirectory = storage_path('app/backup-temp/'.uniqid('files_', true));
        File::ensureDirectoryExists($tempDirectory);

        $tempZipPath = $tempDirectory.'/backup.zip';
        File::put($tempZipPath, Storage::disk($diskName)->get($path));

        $this->embedApplicationFilesInZip($tempZipPath);

        Storage::disk($diskName)->put($path, File::get($tempZipPath));
        File::deleteDirectory($tempDirectory);
    }

    protected function embedApplicationFilesInZip(string $absoluteZipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($absoluteZipPath) !== true) {
            throw new RuntimeException('Unable to open backup archive to embed application files.');
        }

        for ($index = $zip->numFiles - 1; $index >= 0; $index--) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            if (str_starts_with($name, 'files/public/') || str_starts_with($name, 'files/private/')) {
                $zip->deleteIndex($index);
            }
        }

        $this->addDiskFilesToZip($zip, 'public', 'files/public/');
        $this->addDiskFilesToZip($zip, 'local', 'files/private/');

        $zip->close();
    }

    protected function addDiskFilesToZip(ZipArchive $zip, string $diskName, string $zipPrefix): void
    {
        $disk = Storage::disk($diskName);

        foreach ($disk->allFiles() as $relativePath) {
            if ($this->shouldSkipDiskFileForBackup($diskName, $relativePath)) {
                continue;
            }

            $absolutePath = $disk->path($relativePath);

            if (! is_string($absolutePath) || ! is_file($absolutePath)) {
                continue;
            }

            $zip->addFile($absolutePath, $zipPrefix.$relativePath);
        }
    }

    protected function shouldSkipDiskFileForBackup(string $diskName, string $relativePath): bool
    {
        if ($diskName !== $this->backupDiskName()) {
            return false;
        }

        $backupFolder = $this->backupApplicationName().'/';

        return str_starts_with($relativePath, $backupFolder)
            || str_starts_with($relativePath, 'backup-temp/')
            || str_starts_with($relativePath, 'backup-restore/');
    }

    protected function restoreApplicationFilesFromZip(string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open backup archive to restore application files.');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (! is_string($name) || str_ends_with($name, '/')) {
                    continue;
                }

                $diskName = null;
                $relativePath = null;

                if (str_starts_with($name, 'files/public/')) {
                    $diskName = 'public';
                    $relativePath = substr($name, strlen('files/public/'));
                } elseif (str_starts_with($name, 'files/private/')) {
                    $diskName = 'local';
                    $relativePath = substr($name, strlen('files/private/'));
                }

                if ($diskName === null || $relativePath === null || $relativePath === '') {
                    continue;
                }

                if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || str_contains($relativePath, '\\')) {
                    continue;
                }

                $contents = $zip->getFromIndex($index);

                if ($contents === false) {
                    continue;
                }

                Storage::disk($diskName)->put($relativePath, $contents);
            }
        } finally {
            $zip->close();
        }
    }

    protected function embedRestoreTokenOnDisk(string $diskName, string $path, string $plainToken): void
    {
        $tempDirectory = storage_path('app/backup-temp/'.uniqid('token_', true));
        File::ensureDirectoryExists($tempDirectory);

        $tempZipPath = $tempDirectory.'/backup.zip';
        File::put($tempZipPath, Storage::disk($diskName)->get($path));

        $this->embedRestoreTokenInZip($tempZipPath, $plainToken);

        Storage::disk($diskName)->put($path, File::get($tempZipPath));
        File::deleteDirectory($tempDirectory);
    }

    protected function embedRestoreTokenInZip(string $absoluteZipPath, string $plainToken): void
    {
        $zip = new ZipArchive;

        if ($zip->open($absoluteZipPath) !== true) {
            throw new RuntimeException('Unable to open backup archive to embed restore token.');
        }

        $existingIndex = $zip->locateName('RESTORE_TOKEN.txt');

        if ($existingIndex !== false) {
            $zip->deleteIndex($existingIndex);
        }

        $zip->addFromString('RESTORE_TOKEN.txt', $plainToken."\n");
        $zip->close();
    }

    protected function backupDiskName(): string
    {
        $disks = config('backup.backup.destination.disks', ['local']);

        return is_array($disks) ? (string) ($disks[0] ?? 'local') : 'local';
    }

    protected function backupApplicationName(): string
    {
        return (string) config('backup.backup.name', 'laravel-backup');
    }

    protected function extractBackupPayloadFromZip(string $zipPath): string
    {
        $tempDirectory = storage_path('app/backup-restore/'.uniqid('payload_', true));
        File::ensureDirectoryExists($tempDirectory);

        $workingZipPath = $tempDirectory.'/backup.zip';
        File::copy($zipPath, $workingZipPath);

        $zip = new ZipArchive;

        if ($zip->open($workingZipPath) !== true) {
            throw new RuntimeException('Unable to open backup archive.');
        }

        $payloadFilename = null;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            if (str_ends_with($name, '.sql') || str_ends_with($name, '.sqlite')) {
                $payloadFilename = $name;

                break;
            }
        }

        if ($payloadFilename === null) {
            $zip->close();

            throw new RuntimeException('No database dump found in backup archive.');
        }

        $zip->extractTo($tempDirectory, [$payloadFilename]);
        $zip->close();

        return $tempDirectory.'/'.$payloadFilename;
    }

    protected function importSqlDump(string $sqlPath): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        match ($driver) {
            'sqlite' => $this->importSqliteDump($sqlPath, $connection),
            'pgsql' => $this->importPostgresDump($sqlPath, $connection),
            'mysql', 'mariadb' => $this->importMysqlDump($sqlPath, $connection),
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for restore."),
        };
    }

    protected function importSqliteFile(string $sqlitePath): void
    {
        $connection = (string) config('database.default');
        $databasePath = config("database.connections.{$connection}.database");

        if (! is_string($databasePath) || $databasePath === ':memory:') {
            throw new RuntimeException('SQLite file restore requires a file-backed database.');
        }

        DB::disconnect($connection);

        File::copy($sqlitePath, $databasePath);

        DB::purge($connection);
        DB::reconnect($connection);
    }

    protected function importSqliteDump(string $sqlPath, string $connection): void
    {
        $database = config("database.connections.{$connection}.database");

        if ($database === ':memory:') {
            $sql = File::get($sqlPath);
            DB::connection($connection)->unprepared($sql);

            return;
        }

        $process = Process::run([
            'sqlite3',
            $database,
            '.read '.escapeshellarg($sqlPath),
        ]);

        if (! $process->successful()) {
            throw new RuntimeException('SQLite restore failed: '.$process->errorOutput());
        }
    }

    protected function importPostgresDump(string $sqlPath, string $connection): void
    {
        $config = config("database.connections.{$connection}");

        $process = Process::env([
            'PGPASSWORD' => $config['password'] ?? '',
        ])->run([
            'psql',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-p', (string) ($config['port'] ?? '5432'),
            '-U', (string) ($config['username'] ?? 'postgres'),
            '-d', (string) ($config['database'] ?? ''),
            '-v', 'ON_ERROR_STOP=1',
            '-f', $sqlPath,
        ]);

        if (! $process->successful()) {
            throw new RuntimeException('PostgreSQL restore failed: '.$process->errorOutput());
        }
    }

    protected function importMysqlDump(string $sqlPath, string $connection): void
    {
        $config = config("database.connections.{$connection}");

        $process = Process::run([
            'mysql',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-P', (string) ($config['port'] ?? '3306'),
            '-u', (string) ($config['username'] ?? 'root'),
            (string) ($config['password'] ?? '') !== '' ? '-p'.(string) $config['password'] : '',
            (string) ($config['database'] ?? ''),
            '-e', 'source '.addslashes($sqlPath),
        ]);

        if (! $process->successful()) {
            throw new RuntimeException('MySQL restore failed: '.$process->errorOutput());
        }
    }

    protected function flushCaches(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Artisan::call('optimize:clear');
    }
}
