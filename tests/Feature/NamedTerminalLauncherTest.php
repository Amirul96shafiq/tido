<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

test('dev stack scripts launch through a named tido process', function () {
    $package = json_decode((string) file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($package['scripts']['dev:all'])->toStartWith('node scripts/run-named.mjs tido concurrently ')
        ->and($package['scripts']['dev:full'])->toStartWith('node scripts/run-named.mjs tido concurrently ')
        ->and($package['scripts']['dev:whatsapp'])->toStartWith('node scripts/run-named.mjs tido concurrently ')
        ->and(base_path('scripts/win-spawn-with-parent.ps1'))->toBeFile();
});

test('named terminal launcher runs concurrently', function () {
    $result = Process::timeout(60)
        ->path(base_path())
        ->run([
            'node',
            'scripts/run-named.mjs',
            'tido',
            'concurrently',
            '--version',
        ]);

    expect($result->successful())->toBeTrue()
        ->and($result->output().$result->errorOutput())->not->toBeEmpty();
});

test('named terminal launcher rejects a missing command', function () {
    $result = Process::timeout(15)
        ->path(base_path())
        ->run([
            'node',
            'scripts/run-named.mjs',
            'tido',
        ]);

    expect($result->successful())->toBeFalse()
        ->and($result->errorOutput())->toContain('Usage: node scripts/run-named.mjs');
});
