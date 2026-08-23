<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;

uses(RefreshDatabase::class);

test('dashboard viewTabs contract lists finance training health and task', function () {
    expect(Dashboard::viewTabs())->toBe([
        [
            'view' => Dashboard::VIEW_FINANCES,
            'label' => 'Finance',
            'icon' => 'heroicon-m-calculator',
        ],
        [
            'view' => Dashboard::VIEW_TRAINING,
            'label' => 'Training',
            'icon' => 'heroicon-m-bolt',
        ],
        [
            'view' => Dashboard::VIEW_HEALTH,
            'label' => 'Health',
            'icon' => 'heroicon-m-heart',
        ],
        [
            'view' => Dashboard::VIEW_TASK,
            'label' => 'Task',
            'icon' => 'heroicon-m-rectangle-stack',
        ],
    ]);
});

test('dashboard view tabs partial renders the viewTabs catalog and defaults to finances', function () {
    $html = view('filament.pages.partials.dashboard-view-tabs')->render();

    expect($html)
        ->toContain('tido-dashboard-view-tabs')
        ->toContain('Focus:')
        ->toContain('aria-hidden="true"')
        ->toContain('aria-label="Dashboard views"')
        ->toContain('fi-loading-indicator');

    foreach (Dashboard::viewTabs() as $tab) {
        $viewCall = 'setDashboardView('.Js::from($tab['view']).')';

        expect($html)
            ->toContain('aria-label="'.$tab['label'].'"')
            ->toContain($viewCall)
            ->toContain('content: '.Js::from($tab['label']));
    }

    $financeButton = dashboardViewTabsButtonHtml($html, Dashboard::VIEW_FINANCES);
    $trainingButton = dashboardViewTabsButtonHtml($html, Dashboard::VIEW_TRAINING);

    expect($financeButton)
        ->toContain('fi-active')
        ->toContain('aria-current="true"')
        ->and($trainingButton)
        ->not->toContain('fi-active')
        ->not->toContain('aria-current="true"');
});

test('lazy dashboard widgets render a visible loading spinner placeholder', function () {
    $lazyWidgets = [
        \App\Filament\Widgets\BudgetStatus::class,
        \App\Filament\Widgets\MonthlyTrend::class,
        \App\Filament\Widgets\ReceiptsBySource::class,
        \App\Filament\Widgets\RecentReceipts::class,
        \App\Filament\Widgets\SpendingByLabel::class,
        \App\Filament\Widgets\SpendingByPaymentMethod::class,
        \App\Filament\Widgets\TopMerchants::class,
    ];

    foreach ($lazyWidgets as $widgetClass) {
        expect($widgetClass::isLazy())->toBeTrue()
            ->and(class_uses_recursive($widgetClass))
            ->toContain(\App\Filament\Widgets\Concerns\HasDashboardWidgetPlaceholder::class);
    }

    $html = view('filament.widgets.lazy-placeholder', [
        'columnSpan' => [],
        'columnStart' => [],
        'height' => '12rem',
    ])->render();

    expect($html)
        ->toContain('role="status"')
        ->toContain('aria-busy="true"')
        ->toContain('fi-wi-loading-section')
        ->toContain('height: 12rem')
        ->toContain('fi-loading-indicator')
        ->toContain('Loading widget');
});

test('dashboard view tabs partial marks the active view tab', function () {
    $html = view('filament.pages.partials.dashboard-view-tabs', [
        'activeView' => Dashboard::VIEW_TRAINING,
    ])->render();

    $financeButton = dashboardViewTabsButtonHtml($html, Dashboard::VIEW_FINANCES);
    $trainingButton = dashboardViewTabsButtonHtml($html, Dashboard::VIEW_TRAINING);

    expect($trainingButton)
        ->toContain('fi-active')
        ->toContain('aria-current="true"')
        ->and($financeButton)
        ->not->toContain('fi-active')
        ->not->toContain('aria-current="true"');
});

test('dashboard view tabs render hook is scoped to the home dashboard', function () {
    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create();

    $this->actingAs($user);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('tido-dashboard-view-tabs', false);

    $this->get(ExpenseResource::getUrl('index'))
        ->assertSuccessful()
        ->assertDontSee('tido-dashboard-view-tabs', false);
});

function dashboardViewTabsButtonHtml(string $html, string $view): string
{
    $viewCall = 'setDashboardView('.Js::from($view).')';

    preg_match_all('/<button\b[^>]*>/', $html, $matches);

    foreach ($matches[0] as $button) {
        if (str_contains($button, $viewCall)) {
            return $button;
        }
    }

    throw new RuntimeException("Missing dashboard view tab button for [{$view}].");
}
