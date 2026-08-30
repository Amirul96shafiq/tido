<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

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

test('expense table shows payment method labels for qr and touch n go', function () {
    $qrExpense = Expense::factory()->create([
        'payment_method_id' => PaymentMethod::findBySlug('pay_with_qr')->id,
        'merchant_name' => 'QR Merchant',
    ]);
    $tngExpense = Expense::factory()->create([
        'payment_method_id' => PaymentMethod::findBySlug('touchngo')->id,
        'merchant_name' => 'TNG Merchant',
    ]);

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->toggleAllTableColumns()
        ->assertCanSeeTableRecords([$qrExpense, $tngExpense])
        ->assertSee('Pay with QR')
        ->assertSee("Touch 'n Go");
});

test('add receipts table shows payment method labels for qr and touch n go', function () {
    $qrExpense = Expense::factory()->create([
        'payment_method_id' => PaymentMethod::findBySlug('pay_with_qr')->id,
        'original_filename' => 'qr_receipt.jpg',
    ]);
    $tngExpense = Expense::factory()->create([
        'payment_method_id' => PaymentMethod::findBySlug('touchngo')->id,
        'original_filename' => 'tng_receipt.jpg',
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$qrExpense, $tngExpense])
        ->assertSee('Pay with QR')
        ->assertSee("Touch 'n Go");
});

test('seeded payment methods expose icons used by filament badges', function () {
    expect(PaymentMethod::findBySlug('pay_with_qr')?->icon)->toBe('heroicon-o-qr-code')
        ->and(PaymentMethod::findBySlug('touchngo')?->icon)->toBe('heroicon-o-device-phone-mobile')
        ->and(PaymentMethod::findBySlug('cash')?->icon)->toBe('heroicon-o-banknotes');
});
