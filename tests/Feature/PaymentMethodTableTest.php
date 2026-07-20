<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->seed(PaymentMethodSeeder::class);
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('payment methods table shows aliases as comma separated text', function () {
    Livewire::test(ListPaymentMethods::class)
        ->assertSuccessful()
        ->assertSee('master + 3 more')
        ->assertSee('qr + 4 more')
        ->assertDontSee('—, —, —, —');
});

test('invoice table shows payment method labels for qr and touch n go', function () {
    $qrInvoice = Invoice::factory()->create([
        'payment_method_id' => PaymentMethod::findBySlug('pay_with_qr')->id,
        'merchant_name' => 'QR Merchant',
    ]);
    $tngInvoice = Invoice::factory()->create([
        'payment_method_id' => PaymentMethod::findBySlug('touchngo')->id,
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
        'payment_method_id' => PaymentMethod::findBySlug('pay_with_qr')->id,
        'original_filename' => 'qr_receipt.jpg',
    ]);
    $tngInvoice = Invoice::factory()->create([
        'payment_method_id' => PaymentMethod::findBySlug('touchngo')->id,
        'original_filename' => 'tng_receipt.jpg',
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$qrInvoice, $tngInvoice])
        ->assertSee('Pay with QR')
        ->assertSee("Touch 'n Go");
});

test('seeded payment methods expose icons used by filament badges', function () {
    expect(PaymentMethod::findBySlug('pay_with_qr')?->icon)->toBe('heroicon-o-qr-code')
        ->and(PaymentMethod::findBySlug('touchngo')?->icon)->toBe('heroicon-o-device-phone-mobile')
        ->and(PaymentMethod::findBySlug('cash')?->icon)->toBe('heroicon-o-banknotes');
});
