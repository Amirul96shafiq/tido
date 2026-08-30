<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('add receipts page renders sticky section nav markers', function () {
    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('add receipts section nav lists anchor tabs', function () {
    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSee('Add Receipts')
        ->assertSee('Recent Uploads &amp; Processing Status', false)
        ->assertSee('#add-receipts', false)
        ->assertSee('#recent-uploads', false);
});

test('add receipts section nav items match sectionNavItems helper', function () {
    expect(ReceiptUploadPage::sectionNavItems())->toBe([
        ['label' => 'Add Receipts', 'id' => 'add-receipts'],
        ['label' => 'Recent Uploads & Processing Status', 'id' => 'recent-uploads'],
    ]);
});

test('add receipts section nav smooth scrolls on tab click', function () {
    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
