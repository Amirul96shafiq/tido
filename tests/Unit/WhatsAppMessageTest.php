<?php

declare(strict_types=1);

use App\Support\WhatsAppMessage;

test('compose joins header body and footer with blank lines', function () {
    $message = WhatsAppMessage::compose(
        '✅',
        'Test ping',
        "Outbound WhatsApp is working.\n\nSend a receipt photo anytime to start tracking.",
    );

    expect($message)->toBe(
        "✅ *Test ping*\n\nOutbound WhatsApp is working.\n\nSend a receipt photo anytime to start tracking.\n\n— Powered by *tido*",
    )
        ->and($message)->toContain("\n\n")
        ->and($message)->not->toContain('\n');
});

test('compose trims body and uses custom footer', function () {
    $message = WhatsAppMessage::compose('🔐', 'Login code', '  Your code is: *123456*  ', 'Custom footer');

    expect($message)->toBe("🔐 *Login code*\n\nYour code is: *123456*\n\nCustom footer");
});

test('receipt upload failed includes retry count for non-final attempts', function () {
    $message = WhatsAppMessage::receiptUploadFailed(1, 3);

    expect($message)
        ->toContain('*Receipt upload failed (attempt 1 of 3)*')
        ->toContain('retry automatically in about 60 seconds')
        ->not->toContain('final attempt');
});

test('receipt upload failed informs user on final attempt', function () {
    $message = WhatsAppMessage::receiptUploadFailed(3, 3);

    expect($message)
        ->toContain('*Receipt upload failed (attempt 3 of 3)*')
        ->toContain('final attempt')
        ->toContain('Please send the photo again.')
        ->not->toContain('retry automatically');
});
