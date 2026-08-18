<?php

declare(strict_types=1);

use App\Support\ClipboardCopy;
use Tests\TestCase;

uses(TestCase::class);

test('alpine click handler delegates to the clipboard helper', function () {
    $handler = ClipboardCopy::alpineClickHandler('secret-value', 'Copied to clipboard');

    expect($handler)
        ->toContain('window.tidoCopyToClipboard')
        ->toContain("'secret-value'")
        ->toContain('Copied to clipboard')
        ->not->toContain('navigator.clipboard');
});

test('alpine click handler reports a failed copy', function () {
    $handler = ClipboardCopy::alpineClickHandler('secret-value', 'Copied to clipboard');

    expect($handler)
        ->toContain('.success().send()')
        ->toContain('.danger().send()')
        ->toContain('Copy failed, copy it manually');
});

test('alpine click handler escapes values for javascript', function () {
    $handler = ClipboardCopy::alpineClickHandler('a\'b"c</script>', 'Copied to clipboard');

    expect($handler)
        ->not->toContain('</script>')
        ->not->toContain('a\'b"c');
});

test('clipboard helper falls back to execCommand outside secure contexts', function () {
    $script = file_get_contents(resource_path('js/clipboard-copy.js'));

    expect($script)
        ->toContain('window.tidoCopyToClipboard')
        ->toContain('navigator.clipboard.writeText')
        ->toContain("document.execCommand('copy')")
        ->toContain('[x-trap], .fi-modal, dialog')
        ->toContain('selectNodeContents');
});

test('clipboard helper is registered as a panel vite asset', function () {
    $vite = file_get_contents(base_path('vite.config.js'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($vite)
        ->toContain('resources/js/clipboard-copy.js')
        ->and($provider)
        ->toContain("'clipboard-copy'")
        ->toContain("Vite::asset('resources/js/clipboard-copy.js')");
});
