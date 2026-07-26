<?php

declare(strict_types=1);

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('invoice create page renders sticky section nav markers', function () {
    Livewire::test(CreateInvoice::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-marker--bottom', false)
        ->assertSee('tido-section-nav', false);
});

test('invoice edit page renders sticky section nav markers', function () {
    $invoice = Invoice::factory()->create();

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('invoice section nav lists anchor tabs', function () {
    Livewire::test(CreateInvoice::class)
        ->assertSuccessful()
        ->assertSee('Image &amp; Uploads', false)
        ->assertSee('Receipt Details')
        ->assertSee('Invoice Notes')
        ->assertSee('Line Items')
        ->assertSee('Invoice Status')
        ->assertSee('#image-uploads', false)
        ->assertSee('#receipt-details', false)
        ->assertSee('#invoice-notes', false)
        ->assertSee('#line-items', false)
        ->assertSee('#invoice-status', false);
});

test('invoice section nav items match sectionNavItems helper', function () {
    expect(InvoiceForm::sectionNavItems())->toBe([
        ['label' => 'Image & Uploads', 'id' => 'image-uploads'],
        ['label' => 'Receipt Details', 'id' => 'receipt-details'],
        ['label' => 'Invoice Notes', 'id' => 'invoice-notes'],
        ['label' => 'Line Items', 'id' => 'line-items'],
        ['label' => 'Invoice Status', 'id' => 'invoice-status'],
    ]);
});

test('invoice section nav smooth scrolls on tab click', function () {
    Livewire::test(CreateInvoice::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
