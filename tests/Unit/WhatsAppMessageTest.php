<?php

declare(strict_types=1);

use App\Support\WhatsAppMessage;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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

test('help includes updated approaches and command hints', function () {
    $message = WhatsAppMessage::help();

    expect($message)
        ->toContain('🤖 *Help*')
        ->toContain('*document(s)*')
        ->toContain('*image(s)*')
        ->toContain('type *manual* to learn more')
        ->toContain('type *finance others* to learn more')
        ->toContain('— Powered by *tido*');
});

test('finance keywords lists spending commands', function () {
    $message = WhatsAppMessage::financeKeywords();

    expect($message)
        ->toContain('📈 *Finance Keywords*')
        ->toContain('*spend labels* — label breakdown (up to 8)')
        ->toContain('*spend merchants* — top 5 merchants')
        ->toContain('*spend budgets*')
        ->toContain('*spend trend*')
        ->toContain('*spend payment*')
        ->toContain('*spend recent*')
        ->toContain('— Powered by *tido*');
});

test('manual approach includes format sample and payment methods', function () {
    $this->seed(PaymentMethodSeeder::class);

    $message = WhatsAppMessage::manualApproach();

    expect($message)
        ->toContain('💬 *Manual Approach*')
        ->toContain('[Invoice title], [Payment method];')
        ->toContain('ASNB Investment, FPX;')
        ->toContain('Payment method supported:')
        ->toContain('- Cash')
        ->toContain('- Mastercard')
        ->toContain('— Powered by *tido*');
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

test('document received includes file count', function () {
    $message = WhatsAppMessage::documentReceived(2);

    expect($message)->toBe(
        "📥 *Document received*\n\nA total of *2* file(s) saved and queued for AI parsing.\n\n— Powered by *tido*",
    );
});

test('document parsed includes merchant total payment method and invoice edit url', function () {
    $message = WhatsAppMessage::documentParsed(
        'https://tido.test/admin/invoices/1/edit',
        [
            'merchant_name' => '7-Eleven',
            'total_amount' => '12.50',
            'payment_method' => 'Cash',
        ],
    );

    expect($message)->toBe(
        "🎉 *Document parsed*\n\nMerchant: *7-Eleven*\nTotal Amount: *RM 12.50*\nPayment Method: *Cash*\n\nGo to *invoice edit*\nhttps://tido.test/admin/invoices/1/edit\n\n— Powered by *tido*",
    );
});

test('manual invoice received includes invoice count', function () {
    $message = WhatsAppMessage::manualInvoiceReceived(2);

    expect($message)->toBe(
        "📥 *Manual invoice received*\n\nA total of *2* manual invoice(s) saved and queued for AI parsing.\n\n— Powered by *tido*",
    );
});

test('manual invoice parsed includes merchant total payment method and invoice edit url', function () {
    $message = WhatsAppMessage::manualInvoiceParsed(
        'https://tido.test/admin/invoices/191/edit',
        [
            'merchant_name' => 'myNEWS Bayu Residensi',
            'total_amount' => '4.20',
            'payment_method' => 'Cash',
        ],
    );

    expect($message)->toBe(
        "🎉 *Manual invoice parsed*\n\nMerchant: *myNEWS Bayu Residensi*\nTotal Amount: *RM 4.20*\nPayment Method: *Cash*\n\nGo to *invoice edit*\nhttps://tido.test/admin/invoices/191/edit\n\n— Powered by *tido*",
    );
});
