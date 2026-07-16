<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Labels\Pages\ListLabels;
use App\Filament\Widgets\RecentReceipts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('invoices list shows illustrated empty state', function () {
    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertSee('No invoices yet')
        ->assertSee('Upload a receipt or add an invoice to start tracking spending.')
        ->assertSee('Upload Receipts');
});

test('budgets list shows illustrated empty state', function () {
    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertSee('No budgets yet')
        ->assertSee('Create a budget to track spending against a limit.')
        ->assertSee('New budget');
});

test('labels list shows illustrated empty state', function () {
    Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->assertSee('No labels yet')
        ->assertSee('Create a label to categorize expenses.')
        ->assertSee('New label');
});

test('backups list shows illustrated empty state', function () {
    Livewire::test(ListBackups::class)
        ->assertSuccessful()
        ->assertSee('No backups yet')
        ->assertSee('Create a backup to save a restore point.')
        ->assertSee('Create backup');
});

test('recent receipts widget shows illustrated empty state', function () {
    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertSee('No receipts')
        ->assertSee('No receipts recorded for this month.')
        ->assertSee('Upload Receipts');
});

test('receipt upload page table shows illustrated empty state', function () {
    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSee('No receipts yet')
        ->assertSee('Upload a receipt with the form above to start tracking spending.');
});
