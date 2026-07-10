<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('invoice table shows payment method labels for qr and touch n go', function () {
    $qrInvoice = Invoice::factory()->create([
        'payment_method' => PaymentMethod::PayWithQr,
        'merchant_name' => 'QR Merchant',
    ]);
    $tngInvoice = Invoice::factory()->create([
        'payment_method' => PaymentMethod::TouchNGo,
        'merchant_name' => 'TNG Merchant',
    ]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$qrInvoice, $tngInvoice])
        ->assertSee('Pay with QR')
        ->assertSee("Touch 'n Go");
});

test('upload receipts table shows payment method labels for qr and touch n go', function () {
    $qrInvoice = Invoice::factory()->create([
        'payment_method' => PaymentMethod::PayWithQr,
        'original_filename' => 'qr_receipt.jpg',
    ]);
    $tngInvoice = Invoice::factory()->create([
        'payment_method' => PaymentMethod::TouchNGo,
        'original_filename' => 'tng_receipt.jpg',
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$qrInvoice, $tngInvoice])
        ->assertSee('Pay with QR')
        ->assertSee("Touch 'n Go");
});

test('payment method enum exposes icons used by filament badges', function () {
    expect(PaymentMethod::PayWithQr->getIcon())->toBe(Heroicon::QrCode)
        ->and(PaymentMethod::TouchNGo->getIcon())->toBeInstanceOf(Htmlable::class)
        ->and(PaymentMethod::Cash->getIcon())->toBe(Heroicon::Banknotes);
});
