<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Support\BackupManifest;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class BackupService
{
    private const RESTORE_LIMITS_EXCEEDED_MESSAGE = 'Backup archive exceeds restore limits.';

    private static bool $skipScheduledCatalogRegistration = false;

    private ?float $restoreStartedAt = null;

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
        $identity = $this->sealBackupArchive($diskName, $newPath, $filename);

        return Backup::query()->create([
            'type' => BackupType::Auto,
            'disk' => $diskName,
            'path' => $newPath,
            'filename' => $filename,
            'size_bytes' => $disk->exists($newPath) ? $disk->size($newPath) : null,
            'created_by' => null,
            'restore_token_hash' => Hash::make($plainToken),
            'content_sha256' => $identity['content_sha256'],
            'manifest_hmac' => $identity['manifest_hmac'],
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
            $this->restoreBoundArchive($backup, $zipPath, null);
        } finally {
            if (File::isDirectory($tempDirectory)) {
                File::deleteDirectory($tempDirectory);
            }
        }
    }

    public function restoreGuestUpload(Backup $backup, string $zipPath, string $plainToken): void
    {
        $this->restoreBoundArchive($backup, $zipPath, $plainToken);
    }

    public function restoreFromZipPath(string $zipPath): void
    {
        if (! File::exists($zipPath)) {
            throw new RuntimeException('Backup archive was not found.');
        }

        $this->restoreStartedAt = microtime(true);

        try {
            $this->inspectZipResourceLimits($zipPath);

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
        } finally {
            $this->restoreStartedAt = null;
        }
    }

    protected function restoreBoundArchive(Backup $backup, string $zipPath, ?string $plainToken): void
    {
        $store = Cache::store('file')->getStore();

        if (! $store instanceof LockProvider) {
            throw new RuntimeException('A restore is already in progress.');
        }

        $lock = $store->lock('backup-restore', $this->restoreLockTtlSeconds());

        if (! $lock->get()) {
            throw new RuntimeException('A restore is already in progress.');
        }

        $snapshot = null;

        try {
            $backup->refresh();

            if ($plainToken !== null && ! $this->assertRestoreToken($backup, $plainToken)) {
                throw new RuntimeException('Invalid restore token or backup.');
            }

            $this->ensureCatalogIdentity($backup);
            $backup->refresh();
            $this->assertArchiveMatchesCatalog($backup, $zipPath);

            $snapshot = $this->createRestoreSnapshot($zipPath);

            try {
                $this->restoreFromZipPath($zipPath);

                if ($plainToken !== null) {
                    $this->consumeRestoreToken($backup);
                }
            } catch (Throwable $exception) {
                $this->rollbackRestoreSnapshot($snapshot);

                throw $exception;
            }
        } finally {
            if ($snapshot !== null) {
                $this->deleteRestoreSnapshot($snapshot);
            }

            $lock->release();
        }
    }

    public function ensureCatalogIdentity(Backup $backup): void
    {
        if (filled($backup->content_sha256)) {
            return;
        }

        if (! $backup->fileExists()) {
            throw new RuntimeException('Backup archive identity could not be verified.');
        }

        $tempDirectory = storage_path('app/backup-restore/'.uniqid('identity_', true));
        File::ensureDirectoryExists($tempDirectory);

        $zipPath = $tempDirectory.'/backup.zip';
        File::put($zipPath, Storage::disk($backup->disk)->get($backup->path));

        try {
            $identity = $this->readArchiveIdentity($zipPath);

            $backup->forceFill([
                'content_sha256' => $identity['content_sha256'],
                'manifest_hmac' => $identity['manifest_hmac'],
            ])->save();
        } finally {
            if (File::isDirectory($tempDirectory)) {
                File::deleteDirectory($tempDirectory);
            }
        }
    }

    protected function assertArchiveMatchesCatalog(Backup $backup, string $zipPath): void
    {
        $identity = $this->readArchiveIdentity($zipPath);
        $catalogHash = (string) $backup->content_sha256;

        if ($catalogHash === '' || ! hash_equals($catalogHash, $identity['content_sha256'])) {
            throw new RuntimeException('Backup archive identity could not be verified.');
        }

        $catalogHmac = $backup->manifest_hmac;

        if (! filled($catalogHmac)) {
            return;
        }

        if ($identity['manifest_hmac'] === null
            || ! hash_equals((string) $catalogHmac, $identity['manifest_hmac'])
        ) {
            throw new RuntimeException('Backup archive identity could not be verified.');
        }
    }

    /**
     * @return array{content_sha256: string, manifest_hmac: ?string}
     */
    protected function readArchiveIdentity(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open backup archive.');
        }

        try {
            $contentSha256 = BackupManifest::contentSha256($zip);
            $json = $zip->getFromName(BackupManifest::JSON_ENTRY);
            $hmacEntry = $zip->getFromName(BackupManifest::HMAC_ENTRY);

            if ($json === false || $hmacEntry === false) {
                return [
                    'content_sha256' => $contentSha256,
                    'manifest_hmac' => null,
                ];
            }

            $normalizedHmac = strtolower(trim($hmacEntry));

            if (! BackupManifest::hmacIsValid($json, $normalizedHmac)) {
                throw new RuntimeException('Backup archive identity could not be verified.');
            }

            $manifest = BackupManifest::decode($json);

            if ($manifest === null
                || $manifest['v'] !== BackupManifest::VERSION
                || ! hash_equals($contentSha256, $manifest['content_sha256'])
            ) {
                throw new RuntimeException('Backup archive identity could not be verified.');
            }

            return [
                'content_sha256' => $contentSha256,
                'manifest_hmac' => $normalizedHmac,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{content_sha256: string, manifest_hmac: string}
     */
    protected function sealBackupArchive(string $diskName, string $path, string $filename): array
    {
        $tempDirectory = storage_path('app/backup-temp/'.uniqid('seal_', true));
        File::ensureDirectoryExists($tempDirectory);

        $tempZipPath = $tempDirectory.'/backup.zip';
        File::put($tempZipPath, Storage::disk($diskName)->get($path));

        $zip = new ZipArchive;

        if ($zip->open($tempZipPath) !== true) {
            File::deleteDirectory($tempDirectory);

            throw new RuntimeException('Unable to open backup archive to seal identity.');
        }

        try {
            $contentSha256 = BackupManifest::contentSha256($zip);
            $canonicalJson = BackupManifest::encode($filename, $contentSha256);
            $hmac = BackupManifest::hmac($canonicalJson);

            $this->replaceZipStringEntry($zip, BackupManifest::JSON_ENTRY, $canonicalJson);
            $this->replaceZipStringEntry($zip, BackupManifest::HMAC_ENTRY, $hmac."\n");
        } finally {
            $zip->close();
        }

        Storage::disk($diskName)->put($path, File::get($tempZipPath));
        File::deleteDirectory($tempDirectory);

        return [
            'content_sha256' => $contentSha256,
            'manifest_hmac' => $hmac,
        ];
    }

    protected function replaceZipStringEntry(ZipArchive $zip, string $name, string $contents): void
    {
        $existingIndex = $zip->locateName($name);

        if ($existingIndex !== false) {
            $zip->deleteIndex($existingIndex);
        }

        $zip->addFromString($name, $contents);
    }

    /**
     * @return array{directory: string, sqlite: ?string, files: list<array{disk: string, path: string, existed: bool}>}
     */
    protected function createRestoreSnapshot(string $zipPath): array
    {
        $directory = storage_path('app/backup-restore/'.uniqid('snapshot_', true));
        File::ensureDirectoryExists($directory);

        $sqliteSnapshot = null;
        $liveSqlitePath = $this->liveSqliteDatabasePath();

        if ($liveSqlitePath !== null) {
            $sqliteSnapshot = $directory.DIRECTORY_SEPARATOR.'database.sqlite';
            File::copy($liveSqlitePath, $sqliteSnapshot);
        }

        $files = [];

        foreach ($this->listRestorableApplicationFilesFromZip($zipPath) as $file) {
            $existed = Storage::disk($file['disk'])->exists($file['path']);

            if ($existed) {
                $snapshotPath = $directory.DIRECTORY_SEPARATOR.$file['disk'].DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['path']);
                File::ensureDirectoryExists(dirname($snapshotPath));
                File::put($snapshotPath, Storage::disk($file['disk'])->get($file['path']));
            }

            $files[] = [
                'disk' => $file['disk'],
                'path' => $file['path'],
                'existed' => $existed,
            ];
        }

        return [
            'directory' => $directory,
            'sqlite' => $sqliteSnapshot,
            'files' => $files,
        ];
    }

    /**
     * @param  array{directory: string, sqlite: ?string, files: list<array{disk: string, path: string, existed: bool}>}  $snapshot
     */
    protected function rollbackRestoreSnapshot(array $snapshot): void
    {
        if ($snapshot['sqlite'] !== null && File::exists($snapshot['sqlite'])) {
            $connection = (string) config('database.default');
            $databasePath = config("database.connections.{$connection}.database");

            if (is_string($databasePath) && $databasePath !== ':memory:') {
                DB::disconnect($connection);
                File::copy($snapshot['sqlite'], $databasePath);
                DB::purge($connection);
                DB::reconnect($connection);
            }
        }

        foreach ($snapshot['files'] as $file) {
            $snapshotPath = $snapshot['directory'].DIRECTORY_SEPARATOR.$file['disk'].DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['path']);

            if ($file['existed'] && File::exists($snapshotPath)) {
                Storage::disk($file['disk'])->put($file['path'], File::get($snapshotPath));

                continue;
            }

            if (! $file['existed'] && Storage::disk($file['disk'])->exists($file['path'])) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
        }
    }

    /**
     * @param  array{directory: string, sqlite: ?string, files: list<array{disk: string, path: string, existed: bool}>}  $snapshot
     */
    protected function deleteRestoreSnapshot(array $snapshot): void
    {
        if (File::isDirectory($snapshot['directory'])) {
            File::deleteDirectory($snapshot['directory']);
        }
    }

    /**
     * @return list<array{disk: string, path: string}>
     */
    protected function listRestorableApplicationFilesFromZip(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open backup archive.');
        }

        try {
            $files = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (! is_string($name)) {
                    continue;
                }

                $file = BackupManifest::isRestorableApplicationFileEntry($name);

                if ($file !== null) {
                    $files[] = $file;
                }
            }

            return $files;
        } finally {
            $zip->close();
        }
    }

    protected function liveSqliteDatabasePath(): ?string
    {
        $connection = (string) config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            return null;
        }

        $database = config("database.connections.{$connection}.database");

        if (! is_string($database) || $database === ':memory:' || ! is_file($database)) {
            return null;
        }

        return $database;
    }

    protected function restoreLockTtlSeconds(): int
    {
        $maxDurationSeconds = (int) config('backup.backup.restore.max_duration_seconds', 60);

        return max(90, $maxDurationSeconds + 30);
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
        $identity = $this->sealBackupArchive($backup->disk, $backup->path, $backup->filename);

        $backup->forceFill([
            'restore_token_hash' => Hash::make($plainToken),
            'size_bytes' => Storage::disk($backup->disk)->size($backup->path),
            'content_sha256' => $identity['content_sha256'],
            'manifest_hmac' => $identity['manifest_hmac'],
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
        $identity = $this->sealBackupArchive($diskName, $path, $filename);

        return Backup::query()->create([
            'type' => $type,
            'disk' => $diskName,
            'path' => $path,
            'filename' => $filename,
            'size_bytes' => $disk->size($path),
            'created_by' => $createdBy?->getKey(),
            'restore_token_hash' => Hash::make($plainToken),
            'content_sha256' => $identity['content_sha256'],
            'manifest_hmac' => $identity['manifest_hmac'],
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
                $this->assertRestoreDurationNotExceeded();

                $name = $zip->getNameIndex($index);

                if (! is_string($name) || str_ends_with($name, '/')) {
                    continue;
                }

                $file = BackupManifest::isRestorableApplicationFileEntry($name);

                if ($file === null) {
                    continue;
                }

                $contents = $zip->getFromIndex($index);

                if ($contents === false) {
                    continue;
                }

                Storage::disk($file['disk'])->put($file['path'], $contents);
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

    protected function inspectZipResourceLimits(string $zipPath): void
    {
        $this->assertRestoreDurationNotExceeded();

        $compressedBytes = File::size($zipPath);

        if (! is_int($compressedBytes) || $compressedBytes < 0) {
            throw new RuntimeException(self::RESTORE_LIMITS_EXCEEDED_MESSAGE);
        }

        $maxEntries = (int) config('backup.backup.restore.max_entries', 5000);
        $maxUncompressedBytes = (int) config('backup.backup.restore.max_uncompressed_bytes', 209715200);
        $maxEntryBytes = (int) config('backup.backup.restore.max_entry_bytes', 52428800);
        $maxCompressionRatio = (float) config('backup.backup.restore.max_compression_ratio', 100);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open backup archive.');
        }

        try {
            $entryCount = $zip->numFiles;

            if ($entryCount > $maxEntries) {
                throw new RuntimeException(self::RESTORE_LIMITS_EXCEEDED_MESSAGE);
            }

            $totalUncompressedBytes = 0;

            for ($index = 0; $index < $entryCount; $index++) {
                $this->assertRestoreDurationNotExceeded();

                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    throw new RuntimeException(self::RESTORE_LIMITS_EXCEEDED_MESSAGE);
                }

                $entryBytes = (int) ($stat['size'] ?? 0);

                if ($entryBytes < 0 || $entryBytes > $maxEntryBytes) {
                    throw new RuntimeException(self::RESTORE_LIMITS_EXCEEDED_MESSAGE);
                }

                $totalUncompressedBytes += $entryBytes;

                if ($totalUncompressedBytes > $maxUncompressedBytes) {
                    throw new RuntimeException(self::RESTORE_LIMITS_EXCEEDED_MESSAGE);
                }
            }

            if ($compressedBytes > 0 && ($totalUncompressedBytes / $compressedBytes) > $maxCompressionRatio) {
                throw new RuntimeException(self::RESTORE_LIMITS_EXCEEDED_MESSAGE);
            }
        } finally {
            $zip->close();
        }
    }

    protected function assertRestoreDurationNotExceeded(): void
    {
        if ($this->restoreStartedAt === null) {
            return;
        }

        $maxDurationSeconds = (int) config('backup.backup.restore.max_duration_seconds', 60);

        if ((microtime(true) - $this->restoreStartedAt) >= $maxDurationSeconds) {
            throw new RuntimeException(self::RESTORE_LIMITS_EXCEEDED_MESSAGE);
        }
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

        try {
            $sqliteEntry = null;
            $sqlEntries = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $this->assertRestoreDurationNotExceeded();

                $name = $zip->getNameIndex($index);

                if (! is_string($name) || $name === '') {
                    continue;
                }

                if ($this->isNativeSqlitePayloadEntry($name)) {
                    $sqliteEntry = $name;

                    continue;
                }

                if ($this->isSpatieSqlPayloadEntry($name)) {
                    $sqlEntries[] = $name;
                }
            }

            if ($sqliteEntry !== null) {
                return $this->writePayloadEntryToControlledPath(
                    $zip,
                    $sqliteEntry,
                    $tempDirectory,
                    'database.sqlite',
                );
            }

            if (count($sqlEntries) === 1) {
                return $this->writePayloadEntryToControlledPath(
                    $zip,
                    $sqlEntries[0],
                    $tempDirectory,
                    'database.sql',
                );
            }

            if (count($sqlEntries) > 1) {
                throw new RuntimeException('Multiple database dumps found in backup archive.');
            }

            throw new RuntimeException('No database dump found in backup archive.');
        } finally {
            $zip->close();
        }
    }

    protected function isNativeSqlitePayloadEntry(string $name): bool
    {
        return $name === 'database.sqlite';
    }

    protected function isSpatieSqlPayloadEntry(string $name): bool
    {
        if (! str_starts_with($name, 'db-dumps/')) {
            return false;
        }

        if (str_contains($name, '\\') || str_contains($name, "\0")) {
            return false;
        }

        $basename = substr($name, strlen('db-dumps/'));

        if ($basename === '' || str_contains($basename, '/')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.sql$/', $basename);
    }

    protected function writePayloadEntryToControlledPath(
        ZipArchive $zip,
        string $entryName,
        string $tempDirectory,
        string $controlledBasename,
    ): string {
        $this->assertRestoreDurationNotExceeded();

        $contents = $zip->getFromName($entryName);

        if ($contents === false) {
            throw new RuntimeException('Unable to read database dump from backup archive.');
        }

        $resolvedTempDirectory = realpath($tempDirectory);

        if ($resolvedTempDirectory === false) {
            throw new RuntimeException('Unable to resolve backup restore directory.');
        }

        $destinationPath = $resolvedTempDirectory.DIRECTORY_SEPARATOR.$controlledBasename;
        File::put($destinationPath, $contents);

        $resolvedDestination = realpath($destinationPath);

        if ($resolvedDestination === false
            || ! str_starts_with($resolvedDestination, $resolvedTempDirectory.DIRECTORY_SEPARATOR)
            || basename($resolvedDestination) !== $controlledBasename
        ) {
            throw new RuntimeException('Database dump extraction path is invalid.');
        }

        return $resolvedDestination;
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
