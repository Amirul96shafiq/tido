<?php

declare(strict_types=1);

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
});

test('create invoice page shows go back to table link to index', function () {
    $indexUrl = InvoiceResource::getUrl('index');

    Livewire::test(CreateInvoice::class)
        ->assertSee('Go back to table')
        ->assertSee($indexUrl, false);
});

test('edit invoice page shows go back to table link to index', function () {
    $invoice = Invoice::factory()->create();
    $indexUrl = InvoiceResource::getUrl('index');

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSee('Go back to table')
        ->assertSee($indexUrl, false);
});

test('list invoices page does not show go back to table link', function () {
    Livewire::test(ListInvoices::class)
        ->assertDontSee('Go back to table');
});
