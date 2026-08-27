<?php

declare(strict_types=1);

test('auth login toast component is removed', function () {
    expect(file_exists(resource_path('views/components/auth-login-toast.blade.php')))->toBeFalse();
});

test('auth login toast css is removed', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->not->toContain('.tido-auth-login-toast')
        ->not->toContain('.tido-auth-login-toast-modal')
        ->not->toContain('.tido-auth-login-toast-modal-backdrop')
        ->not->toContain('.tido-auth-login-toast-modal-panel');
});
