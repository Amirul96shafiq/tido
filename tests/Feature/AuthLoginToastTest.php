<?php

declare(strict_types=1);

test('auth login toast blade gates mobile modal to phone and password modes', function () {
    $blade = (string) file_get_contents(resource_path('views/components/auth-login-toast.blade.php'));

    expect($blade)
        ->toContain('tido-auth-login-toast-root')
        ->toContain('tido-auth-login-toast-modal')
        ->toContain('tido-auth-login-toast-modal-panel')
        ->toContain("\$wire.entangle('loginMode')")
        ->toContain("this.loginMode === 'phone' || this.loginMode === 'password'")
        ->toContain('showModal')
        ->toContain('showInline')
        ->toContain('max-width: 1023px')
        ->not->toContain("loginMode === 'otp'");
});

test('auth login toast modal css is present', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.tido-auth-login-toast-modal {')
        ->toContain('.tido-auth-login-toast-modal-backdrop {')
        ->toContain('.tido-auth-login-toast-modal-panel {')
        ->toContain('position: fixed;');
});
