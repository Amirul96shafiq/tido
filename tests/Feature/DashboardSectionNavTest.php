<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\MonthlySpendingOverview;
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
        ->assertSee('tido-section-nav', false);
});

test('dashboard section nav lists all widgets as anchor tabs', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Total Spent')
        ->assertSee('Spending Forecast')
        ->assertSee('SST Tax Paid')
        ->assertSee('Receipts Processed')
        ->assertSee('Monthly Spending Trend')
        ->assertSee('Spending by Label')
        ->assertSee('Budget Performance')
        ->assertSee('Top Merchants')
        ->assertSee('Spending by Payment Method')
        ->assertSee('Receipts by Upload Source')
        ->assertSee('Recent Receipts')
        ->assertSee('#'.MonthlySpendingOverview::SECTION_TOTAL_SPENT, false)
        ->assertSee('#'.MonthlySpendingOverview::SECTION_SPENDING_FORECAST, false)
        ->assertSee('#'.MonthlySpendingOverview::SECTION_SST_TAX_PAID, false)
        ->assertSee('#'.MonthlySpendingOverview::SECTION_RECEIPTS_PROCESSED, false)
        ->assertSee('#monthly-trend', false)
        ->assertSee('#spending-by-label', false)
        ->assertSee('#budget-status', false)
        ->assertSee('#top-merchants', false)
        ->assertSee('#spending-by-payment-method', false)
        ->assertSee('#receipts-by-source', false)
        ->assertSee('#recent-receipts', false);
});

test('dashboard section nav items match widgetNavItems helper for current month', function () {
    $component = Livewire::test(Dashboard::class);

    expect($component->instance()->widgetNavItems())->toBe([
        ['label' => 'Total Spent', 'id' => MonthlySpendingOverview::SECTION_TOTAL_SPENT],
        ['label' => 'Spending Forecast', 'id' => MonthlySpendingOverview::SECTION_SPENDING_FORECAST],
        ['label' => 'SST Tax Paid', 'id' => MonthlySpendingOverview::SECTION_SST_TAX_PAID],
        ['label' => 'Receipts Processed', 'id' => MonthlySpendingOverview::SECTION_RECEIPTS_PROCESSED],
        ['label' => 'Monthly Spending Trend', 'id' => 'monthly-trend'],
        ['label' => 'Spending by Label', 'id' => 'spending-by-label'],
        ['label' => 'Budget Performance', 'id' => 'budget-status'],
        ['label' => 'Top Merchants', 'id' => 'top-merchants'],
        ['label' => 'Spending by Payment Method', 'id' => 'spending-by-payment-method'],
        ['label' => 'Receipts by Upload Source', 'id' => 'receipts-by-source'],
        ['label' => 'Recent Receipts', 'id' => 'recent-receipts'],
    ]);
});

test('dashboard section nav uses daily average label for past months', function () {
    $component = Livewire::test(Dashboard::class)
        ->set('filters.month', now()->subMonth()->format('Y-m'));

    $forecastItem = collect($component->instance()->widgetNavItems())
        ->first(fn (array $item): bool => $item['id'] === MonthlySpendingOverview::SECTION_SPENDING_FORECAST);

    expect($forecastItem)->toBe([
        'label' => 'Daily Average',
        'id' => MonthlySpendingOverview::SECTION_SPENDING_FORECAST,
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
        ->assertSee('tido-section-nav__fade--left', false)
        ->assertSee('tido-section-nav__fade--right', false)
        ->assertSee('tido-section-nav--can-scroll-left', false)
        ->assertSee('tido-section-nav--can-scroll-right', false)
        ->assertSee('scrollActiveTabIntoView', false);
});

test('dashboard section nav supports click drag horizontal scroll', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('isDragging', false)
        ->assertSee('dragMoved', false)
        ->assertSee('onTabPointerDown', false)
        ->assertSee('onTabPointerMove', false)
        ->assertSee('endTabDrag', false)
        ->assertSee('setPointerCapture', false)
        ->assertSee('dragThreshold', false)
        ->assertSee('tido-section-nav--dragging', false)
        ->assertSee("dragstart', (event) => event.preventDefault()", false)
        ->assertSee('draggable="false"', false);
});

test('dashboard widgets expose section anchor ids', function () {
    $html = Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->html();

    expect($html)
        ->toContain('id="'.MonthlySpendingOverview::SECTION_TOTAL_SPENT.'"')
        ->toContain('id="'.MonthlySpendingOverview::SECTION_SPENDING_FORECAST.'"')
        ->toContain('id="'.MonthlySpendingOverview::SECTION_SST_TAX_PAID.'"')
        ->toContain('id="'.MonthlySpendingOverview::SECTION_RECEIPTS_PROCESSED.'"')
        ->toContain('id="monthly-trend"')
        ->toContain('id="recent-receipts"')
        ->not->toContain('id="overview"');
});

test('dashboard stat card anchors include scroll margin offset', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.tido-dashboard-page .fi-wi-stats-overview-stat[id]')
        ->toContain('scroll-margin-top');
});
