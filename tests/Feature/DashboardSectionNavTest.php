<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CurrentCurrency;
use App\Filament\Widgets\MonthlySpendingOverview;
use App\Filament\Widgets\RecurringMonthSnapshot;
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
        ->assertSee('USD to MYR')
        ->assertSee('Due Recurrings')
        ->assertSee(RecurringMonthSnapshot::headingLabel())
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
        ->assertSee('#'.CurrentCurrency::SECTION_CURRENCY_RATE, false)
        ->assertSee('#due-recurrings', false)
        ->assertSee('#recurring-month-snapshot', false)
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
        ['label' => 'USD to MYR', 'id' => CurrentCurrency::SECTION_CURRENCY_RATE],
        ['label' => 'Due Recurrings', 'id' => 'due-recurrings'],
        ['label' => RecurringMonthSnapshot::headingLabel(), 'id' => RecurringMonthSnapshot::dashboardSectionId()],
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
        ->assertSee('scrollActiveTabIntoView', false)
        ->assertSee('resetTabsScrollAtPageTop', false);
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
        ->toContain('id="'.CurrentCurrency::SECTION_CURRENCY_RATE.'"')
        ->toContain('id="due-recurrings"')
        ->toContain('id="recurring-month-snapshot"')
        ->not->toContain('id="overview"');
});

test('due recurrings widget renders after currency overview', function () {
    $html = (string) Livewire::test(Dashboard::class)->html();

    $currencyPos = strpos($html, 'id="'.CurrentCurrency::SECTION_CURRENCY_RATE.'"');
    $duePos = strpos($html, 'id="due-recurrings"');
    $snapshotPos = strpos($html, 'id="recurring-month-snapshot"');

    expect($currencyPos)->not->toBeFalse()
        ->and($duePos)->not->toBeFalse()
        ->and($snapshotPos)->not->toBeFalse()
        ->and($currencyPos)->toBeLessThan($duePos)
        ->and($duePos)->toBeLessThan($snapshotPos);
});

test('dashboard sticky toolbar uses three-quarter quarter grid layout', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.tido-dashboard-sticky-toolbar {')
        ->toContain('grid-template-columns: minmax(0, 3fr) minmax(0, 1fr)')
        ->toContain('tido-dashboard-sticky-toolbar-filters')
        ->toContain('tido-dashboard-sticky-toolbar-nav');
});

test('dashboard sticky toolbar places section nav before filters', function () {
    $html = (string) Livewire::test(Dashboard::class)->html();
    $navPos = strpos($html, 'tido-dashboard-sticky-toolbar-nav');
    $filtersPos = strpos($html, 'tido-dashboard-sticky-toolbar-filters');

    expect($navPos)->not->toBeFalse()
        ->and($filtersPos)->not->toBeFalse()
        ->and($navPos)->toBeLessThan($filtersPos);
});

test('dashboard renders sticky toolbar partial with filter and section nav', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('tido-dashboard-sticky-toolbar', false)
        ->assertSee('tido-dashboard-sticky-toolbar-filters', false)
        ->assertSee('tido-dashboard-sticky-toolbar-nav', false)
        ->assertSee('tido-dashboard-filters-dropdown', false)
        ->assertSee('tido-section-nav', false);
});

test('dashboard stat card anchors include scroll margin offset', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.tido-dashboard-page .fi-wi-stats-overview-stat[id]')
        ->toContain('scroll-margin-top');
});

test('analytics chart grid lines use a visible dark mode color', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.dark .fi-wi-chart .fi-wi-chart-grid-color {')
        ->toContain('color: color-mix(in srgb, var(--color-slate-700) 35%, transparent);');
});

test('dashboard currency card fills the shared overview row', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.tido-dashboard-page #currency-rate.fi-wi-current-currency')
        ->toContain('.tido-dashboard-page #currency-rate.fi-wi-current-currency > .fi-section')
        ->toContain('.fi-wi-currency-rate-sparkline')
        ->toContain('.fi-wi-current-currency-surface');
});

test('dashboard stats overview distributes four cards to match currency row height', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.tido-dashboard-page .fi-wi-stats-overview > .fi-sc.fi-grid')
        ->toContain('grid-auto-rows: minmax(0, 1fr)')
        ->toContain('.tido-dashboard-page .fi-wi-stats-overview-stat[id]');
});
