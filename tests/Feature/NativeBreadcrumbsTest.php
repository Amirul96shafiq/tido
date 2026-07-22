<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ReceiptUploadPage;
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

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
});

test('create invoice page shows native breadcrumbs to index', function () {
    $indexUrl = InvoiceResource::getUrl('index');
    $homeUrl = Dashboard::getUrl();

    Livewire::test(CreateInvoice::class)
        ->assertSee('fi-breadcrumbs', false)
        ->assertSee('Home')
        ->assertSee($homeUrl, false)
        ->assertSee($indexUrl, false)
        ->assertDontSee('Go back to table');
});

test('edit invoice page shows native breadcrumbs to index', function () {
    $invoice = Invoice::factory()->create();
    $indexUrl = InvoiceResource::getUrl('index');
    $homeUrl = Dashboard::getUrl();

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSee('fi-breadcrumbs', false)
        ->assertSee('Home')
        ->assertSee($homeUrl, false)
        ->assertSee($indexUrl, false)
        ->assertDontSee('Go back to table');
});

test('list invoices page shows home invoices list breadcrumbs', function () {
    $indexUrl = InvoiceResource::getUrl('index');
    $homeUrl = Dashboard::getUrl();

    Livewire::test(ListInvoices::class)
        ->assertSee('fi-breadcrumbs', false)
        ->assertSee('Home')
        ->assertSee($homeUrl, false)
        ->assertSee('Invoices')
        ->assertSee('List')
        ->assertSee($indexUrl, false)
        ->assertDontSee('Go back to table');
});

test('custom receipt upload page shows home and title breadcrumbs', function () {
    $homeUrl = Dashboard::getUrl();

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSee('fi-breadcrumbs', false)
        ->assertSee('Home')
        ->assertSee($homeUrl, false)
        ->assertSee('Upload Receipts');
});

test('app css keeps breadcrumbs visible below the sm breakpoint', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $block = Str::between(
        $css,
        '.fi-header .fi-breadcrumbs {',
        '.fi-header .fi-breadcrumbs .fi-breadcrumbs-item-label {',
    );

    expect($block)
        ->toContain('display: block;')
        ->toContain('min-width: 0;')
        ->and($css)
        ->toContain('.fi-header .fi-breadcrumbs .fi-breadcrumbs-item-label {')
        ->toContain('padding-block: 0.25rem;');
});
