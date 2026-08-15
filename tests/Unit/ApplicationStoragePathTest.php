<?php

declare(strict_types=1);

use App\Support\ApplicationStoragePath;

beforeEach(function (): void {
    $this->originalStoragePath = app()->storagePath();
    $this->originalStorageEnv = env('APP_STORAGE_PATH');
});

afterEach(function (): void {
    unset($_ENV['APP_STORAGE_PATH'], $_SERVER['APP_STORAGE_PATH']);

    if (is_string($this->originalStorageEnv) && $this->originalStorageEnv !== '') {
        putenv('APP_STORAGE_PATH='.$this->originalStorageEnv);
        $_ENV['APP_STORAGE_PATH'] = $this->originalStorageEnv;
        $_SERVER['APP_STORAGE_PATH'] = $this->originalStorageEnv;
    } else {
        putenv('APP_STORAGE_PATH');
    }

    app()->useStoragePath($this->originalStoragePath);
});

test('relative APP_STORAGE_PATH is resolved against the application base path', function (): void {
    putenv('APP_STORAGE_PATH=storage/sandbox-test-path');
    $_ENV['APP_STORAGE_PATH'] = 'storage/sandbox-test-path';
    $_SERVER['APP_STORAGE_PATH'] = 'storage/sandbox-test-path';

    ApplicationStoragePath::applyFromEnvironment(app());

    expect(app()->storagePath())->toBe(app()->basePath('storage/sandbox-test-path'));
});

test('absolute APP_STORAGE_PATH is used as the storage path', function (): void {
    $absolutePath = app()->basePath('storage/sandbox-absolute');

    putenv('APP_STORAGE_PATH='.$absolutePath);
    $_ENV['APP_STORAGE_PATH'] = $absolutePath;
    $_SERVER['APP_STORAGE_PATH'] = $absolutePath;

    ApplicationStoragePath::applyFromEnvironment(app());

    expect(app()->storagePath())->toBe($absolutePath);
});

test('empty APP_STORAGE_PATH leaves the storage path unchanged', function (): void {
    putenv('APP_STORAGE_PATH=');
    $_ENV['APP_STORAGE_PATH'] = '';
    $_SERVER['APP_STORAGE_PATH'] = '';

    ApplicationStoragePath::applyFromEnvironment(app());

    expect(app()->storagePath())->toBe($this->originalStoragePath);
});
