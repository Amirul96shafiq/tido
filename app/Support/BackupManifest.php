<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use ZipArchive;

final class BackupManifest
{
    public const VERSION = 1;

    public const JSON_ENTRY = 'MANIFEST.json';

    public const HMAC_ENTRY = 'MANIFEST.hmac';

    public const TOKEN_ENTRY = 'RESTORE_TOKEN.txt';

    /**
     * @var list<string>
     */
    private const APPLICATION_FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

    public static function isIdentityEntry(string $name): bool
    {
        if ($name === '' || str_ends_with($name, '/')) {
            return false;
        }

        if ($name === 'database.sqlite') {
            return true;
        }

        if (self::isSpatieSqlPayloadEntry($name)) {
            return true;
        }

        return self::isRestorableApplicationFileEntry($name) !== null;
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    public static function isRestorableApplicationFileEntry(string $name): ?array
    {
        if (str_starts_with($name, 'files/public/')) {
            return self::applicationFileFromRelativePath('public', substr($name, strlen('files/public/')));
        }

        if (str_starts_with($name, 'files/private/')) {
            return self::applicationFileFromRelativePath('local', substr($name, strlen('files/private/')));
        }

        return null;
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    private static function applicationFileFromRelativePath(string $diskName, string $relativePath): ?array
    {
        if ($relativePath === '') {
            return null;
        }

        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || str_contains($relativePath, '\\')) {
            return null;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if (! in_array($extension, self::APPLICATION_FILE_EXTENSIONS, true)) {
            return null;
        }

        return [
            'disk' => $diskName,
            'path' => $relativePath,
        ];
    }

    public static function contentSha256(ZipArchive $zip): string
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name) || ! self::isIdentityEntry($name)) {
                continue;
            }

            $contents = $zip->getFromIndex($index);

            if ($contents === false) {
                throw new RuntimeException('Unable to read backup archive identity entry.');
            }

            $entries[$name] = $contents;
        }

        ksort($entries, SORT_STRING);

        $context = hash_init('sha256');

        foreach ($entries as $name => $contents) {
            hash_update($context, $name."\0".strlen($contents)."\0".$contents);
        }

        return hash_final($context);
    }

    public static function encode(string $filename, string $contentSha256): string
    {
        $payload = [
            'content_sha256' => $contentSha256,
            'filename' => $filename,
            'v' => self::VERSION,
        ];

        ksort($payload);

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public static function hmac(string $canonicalJson): string
    {
        return hash_hmac('sha256', $canonicalJson, (string) config('app.key'));
    }

    public static function hmacIsValid(string $canonicalJson, string $hmac): bool
    {
        $normalizedHmac = strtolower(trim($hmac));

        if ($normalizedHmac === '' || strlen($normalizedHmac) !== 64) {
            return false;
        }

        return hash_equals(self::hmac($canonicalJson), $normalizedHmac);
    }

    /**
     * @return array{content_sha256: string, filename: string, v: int}|null
     */
    public static function decode(string $canonicalJson): ?array
    {
        try {
            $decoded = json_decode($canonicalJson, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $contentSha256 = $decoded['content_sha256'] ?? null;
        $filename = $decoded['filename'] ?? null;
        $version = $decoded['v'] ?? null;

        if (! is_string($contentSha256) || $contentSha256 === '' || ! is_string($filename) || ! is_int($version)) {
            return null;
        }

        return [
            'content_sha256' => $contentSha256,
            'filename' => $filename,
            'v' => $version,
        ];
    }

    private static function isSpatieSqlPayloadEntry(string $name): bool
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
}
