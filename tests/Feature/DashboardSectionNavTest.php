<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('dashboard renders sticky toolbar with widget section nav', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-dashboard-sticky-toolbar', false)
        ->assertSee('tido-profile-section-nav', false);
});

test('dashboard section nav lists all widgets as anchor tabs', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Overview')
        ->assertSee('Trend')
        ->assertSee('Labels')
        ->assertSee('Budgets')
        ->assertSee('Merchants')
        ->assertSee('Payments')
        ->assertSee('Sources')
        ->assertSee('Recent')
        ->assertSee('#overview', false)
        ->assertSee('#monthly-trend', false)
        ->assertSee('#spending-by-label', false)
        ->assertSee('#budget-status', false)
        ->assertSee('#top-merchants', false)
        ->assertSee('#spending-by-payment-method', false)
        ->assertSee('#receipts-by-source', false)
        ->assertSee('#recent-receipts', false);
});

test('dashboard section nav items match widgetNavItems helper', function () {
    expect(Dashboard::widgetNavItems())->toBe([
        ['label' => 'Overview', 'id' => 'overview'],
        ['label' => 'Trend', 'id' => 'monthly-trend'],
        ['label' => 'Labels', 'id' => 'spending-by-label'],
        ['label' => 'Budgets', 'id' => 'budget-status'],
        ['label' => 'Merchants', 'id' => 'top-merchants'],
        ['label' => 'Payments', 'id' => 'spending-by-payment-method'],
        ['label' => 'Sources', 'id' => 'receipts-by-source'],
        ['label' => 'Recent', 'id' => 'recent-receipts'],
    ]);
});

test('dashboard section nav smooth scrolls on tab click', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false)
        ->assertSee('x-on:click.capture', false);
});

test('dashboard section nav exposes horizontal scroll hint affordances', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('updateScrollHints', false)
        ->assertSee('canScrollLeft', false)
        ->assertSee('canScrollRight', false)
        ->assertSee('tido-profile-section-nav__fade--left', false)
        ->assertSee('tido-profile-section-nav__fade--right', false)
        ->assertSee('tido-profile-section-nav--can-scroll-left', false)
        ->assertSee('tido-profile-section-nav--can-scroll-right', false)
        ->assertSee('scrollActiveTabIntoView', false);
});

test('dashboard widgets expose section anchor ids', function () {
    $html = Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->html();

    expect($html)
        ->toContain('id="overview"')
        ->toContain('id="monthly-trend"')
        ->toContain('id="recent-receipts"');
});
