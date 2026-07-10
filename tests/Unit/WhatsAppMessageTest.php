<?php

declare(strict_types=1);

use App\Support\WhatsAppMessage;

test('compose joins header body and footer with blank lines', function () {
    $message = WhatsAppMessage::compose(
        '✅',
        'Test ping',
        "Outbound WhatsApp delivery is working correctly.\n\nSend a document anytime to start tracking expenses.",
    );

    expect($message)->toBe(
        "✅ *Test ping*\n\nOutbound WhatsApp delivery is working correctly.\n\nSend a document anytime to start tracking expenses.\n\n— Powered by *tido*",
    )
        ->and($message)->toContain("\n\n")
        ->and($message)->not->toContain('\n');
});

test('compose trims body and uses custom footer', function () {
    $message = WhatsAppMessage::compose('🔐', 'Login code', '  Code: *123456*  ', 'Custom footer');

    expect($message)->toBe("🔐 *Login code*\n\nCode: *123456*\n\nCustom footer");
});

test('receipt upload failed includes retry count for non-final attempts', function () {
    $message = WhatsAppMessage::receiptUploadFailed(1, 3);

    expect($message)
        ->toContain('*Upload failed (attempt 1 of 3)*')
        ->toContain('Automatic retry in about 60 seconds')
        ->not->toContain('final attempt');
});

test('receipt upload failed informs user on final attempt', function () {
    $message = WhatsAppMessage::receiptUploadFailed(3, 3);

    expect($message)
        ->toContain('*Upload failed (attempt 3 of 3)*')
        ->toContain('final attempt')
        ->toContain('Resend the document to try again.')
        ->not->toContain('Automatic retry');
});
