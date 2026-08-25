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
        ->toContain('*spend recurrings*')
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
        ->toContain('[Expense title], [Payment method];')
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

test('document parsed includes merchant total payment method and expense edit url', function () {
    $message = WhatsAppMessage::documentParsed(
        'https://tido.test/admin/expenses/1/edit',
        [
            'merchant_name' => '7-Eleven',
            'total_amount' => '12.50',
            'payment_method' => 'Cash',
        ],
    );

    expect($message)->toBe(
        "🎉 *Document parsed*\n\nMerchant: *7-Eleven*\nTotal Amount: *RM 12.50*\nPayment Method: *Cash*\n\nGo to *expense edit*\nhttps://tido.test/admin/expenses/1/edit\n\n— Powered by *tido*",
    );
});

test('document needs review includes merchant total payment method and review note', function () {
    $message = WhatsAppMessage::documentNeedsReview(
        'https://tido.test/admin/expenses/1/edit',
        [
            'merchant_name' => 'Luckin Coffee',
            'total_amount' => '4.23',
            'payment_method' => 'Other',
        ],
    );

    expect($message)->toBe(
        "⚠️ *Document needs review*\n\nMerchant: *Luckin Coffee*\nTotal Amount: *RM 4.23*\nPayment Method: *Other*\n\nGo to *expense edit*\nhttps://tido.test/admin/expenses/1/edit\n\nPlease review and confirm the details in the admin panel.\n\n— Powered by *tido*",
    );
});

test('non-receipt document message avoids fabricated financial details', function () {
    $message = WhatsAppMessage::documentNotReceipt(
        'https://tido.test/admin/expenses/2/edit',
    );

    expect($message)
        ->toContain('⚠️ *Non-receipt document*')
        ->toContain('does not appear to contain receipt information')
        ->toContain('saved as an expense for manual review')
        ->toContain('excluded from spending analytics')
        ->toContain('https://tido.test/admin/expenses/2/edit')
        ->not->toContain('Merchant:')
        ->not->toContain('Total Amount:');
});

test('manual expense received includes expense count', function () {
    $message = WhatsAppMessage::manualExpenseReceived(2);

    expect($message)->toBe(
        "📥 *Manual expense received*\n\nA total of *2* manual expense(s) saved and queued for AI parsing.\n\n— Powered by *tido*",
    );
});

test('manual expense parsed includes merchant total payment method and expense edit url', function () {
    $message = WhatsAppMessage::manualExpenseParsed(
        'https://tido.test/admin/expenses/191/edit',
        [
            'merchant_name' => 'myNEWS Bayu Residensi',
            'total_amount' => '4.20',
            'payment_method' => 'Cash',
        ],
    );

    expect($message)->toBe(
        "🎉 *Manual expense parsed*\n\nMerchant: *myNEWS Bayu Residensi*\nTotal Amount: *RM 4.20*\nPayment Method: *Cash*\n\nGo to *expense edit*\nhttps://tido.test/admin/expenses/191/edit\n\n— Powered by *tido*",
    );
});

test('recurring reminder summary formats counts items and index url', function () {
    $message = WhatsAppMessage::recurringReminderSummary(
        'http://tido.local/admin/recurrings',
        [
            [
                'title' => 'Home Financing-i',
                'amount' => 'RM 1,327.00',
                'due_on' => '10 Aug 2026',
                'is_overdue' => true,
            ],
            [
                'title' => 'Tabung Raya 2027',
                'amount' => 'RM 50.00',
                'due_on' => '24 Aug 2026',
                'is_overdue' => false,
            ],
        ],
    );

    expect($message)->toBe(
        "⏰ *Recurring payment summary*\n\n*2 payments · 1 overdue*\n\n• *Home Financing-i*\n  Amount: RM 1,327.00\n  Overdue: 10 Aug 2026\n\n• *Tabung Raya 2027*\n  Amount: RM 50.00\n  Due: 24 Aug 2026\n\nView recurrings\nhttp://tido.local/admin/recurrings\n\n— Powered by *tido*",
    );
});

test('recurring reminder summary with only overdue items uses overdue count line', function () {
    $message = WhatsAppMessage::recurringReminderSummary(
        'http://tido.local/admin/recurrings',
        [
            [
                'title' => 'Home Financing-i',
                'amount' => 'RM 1,327.00',
                'due_on' => '10 Aug 2026',
                'is_overdue' => true,
            ],
        ],
    );

    expect($message)
        ->toContain('⏰ *Recurring payment summary*')
        ->toContain('*1 overdue payment*')
        ->toContain('Overdue: 10 Aug 2026')
        ->toContain('View recurrings')
        ->toContain('http://tido.local/admin/recurrings');
});

test('recurring reminder summary without overdue uses calendar emoji', function () {
    $message = WhatsAppMessage::recurringReminderSummary(
        'http://tido.local/admin/recurrings',
        [
            [
                'title' => 'Tabung Raya 2027',
                'amount' => 'RM 50.00',
                'due_on' => '24 Aug 2026',
                'is_overdue' => false,
            ],
        ],
    );

    expect($message)
        ->toContain('📅 *Recurring payment summary*')
        ->toContain('*1 payment in your reminder window*')
        ->toContain('Due: 24 Aug 2026');
});
