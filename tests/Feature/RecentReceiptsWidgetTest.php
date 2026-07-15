<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Filament\Widgets\RecentReceipts;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('recent receipts widget shows upload table columns', function () {
    $invoice = Invoice::factory()->create([
        'original_filename' => 'dashboard_receipt.jpg',
        'image_path' => 'receipts/dashboard_receipt.jpg',
        'merchant_name' => 'Widget Merchant',
        'payment_method' => PaymentMethod::Cash,
        'source' => 'manual',
        'status' => 'reviewed',
        'date_time' => now(),
        'total_amount' => 12.50,
    ]);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertSeeHtml('fi-wi-recent-receipts')
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('dashboard_....jpg')
        ->assertSee('Widget Merchant')
        ->assertSee('Cash')
        ->assertCanRenderTableColumn('original_filename')
        ->assertCanRenderTableColumn('payment_method')
        ->assertCanRenderTableColumn('source')
        ->assertCanRenderTableColumn('created_at');
});

test('recent receipts widget filename links to file in a new tab', function () {
    Storage::fake();

    $path = 'receipts/dashboard_receipt.jpg';
    Storage::put($path, 'fake-image-bytes');

    $invoice = Invoice::factory()->create([
        'original_filename' => 'dashboard_receipt.jpg',
        'image_path' => $path,
        'date_time' => now(),
    ]);

    $url = Storage::temporaryUrl($path, now()->addMinutes(30));

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml(e($url));
});

test('recent receipts widget excludes invoices outside selected month', function () {
    $inMonth = Invoice::factory()->create([
        'merchant_name' => 'This Month',
        'date_time' => now(),
    ]);

    $outOfMonth = Invoice::factory()->create([
        'merchant_name' => 'Last Year',
        'date_time' => now()->subYear(),
    ]);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$inMonth])
        ->assertCanNotSeeTableRecords([$outOfMonth]);
});
