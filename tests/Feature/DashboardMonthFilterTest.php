<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Support\DashboardSpenderScope;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function resetMonthAction(): TestAction
{
    return TestAction::make('resetMonth')
        ->schemaComponent('resetMonthActions', schema: 'filtersForm');
}

function previousMonthAction(): TestAction
{
    return TestAction::make('previousMonth')
        ->schemaComponent('month', schema: 'filtersForm');
}

test('dashboard renders filter dropdown trigger in sticky toolbar', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertActionVisible(resetMonthAction());

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('tido-dashboard-filters-dropdown', false)
        ->assertSee('fi-dashboard-filters-trigger', false)
        ->assertSee('aria-label="Filters"', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-scope', false);
});

test('dashboard filter dropdown shows active count when month is not current', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSet('filters.month', '2026-07')
        ->assertOk();

    expect(Livewire::test(Dashboard::class)->instance()->dashboardFiltersActiveCount())->toBe(0);

    Livewire::test(Dashboard::class)
        ->set('filters.month', '2026-05')
        ->assertSet('filters.month', '2026-05');

    expect(Livewire::test(Dashboard::class)
        ->set('filters.month', '2026-05')
        ->instance()
        ->dashboardFiltersActiveCount())->toBe(1);
});

test('spender filter updates live on the dashboard component', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->set('filters.spender', DashboardSpenderScope::PRIMARY)
        ->assertSet('filters.spender', DashboardSpenderScope::PRIMARY);
});

test('reset month action restores current calendar month', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->set('filters.month', '2026-05')
        ->assertSet('filters.month', '2026-05')
        ->callAction(resetMonthAction())
        ->assertSet('filters.month', '2026-07');
});

test('reset month action preserves spender filter', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->set('filters.spender', DashboardSpenderScope::PRIMARY)
        ->set('filters.month', '2026-05')
        ->callAction(resetMonthAction())
        ->assertSet('filters.month', '2026-07')
        ->assertSet('filters.spender', DashboardSpenderScope::PRIMARY);
});

test('shifting dashboard month preserves spender filter', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->set('filters.spender', DashboardSpenderScope::PRIMARY)
        ->set('filters.month', '2026-07')
        ->callAction(previousMonthAction())
        ->assertSet('filters.month', '2026-06')
        ->assertSet('filters.spender', DashboardSpenderScope::PRIMARY);
});

test('reset month action is disabled when current month is selected', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSet('filters.month', '2026-07')
        ->assertActionDisabled(resetMonthAction());
});

afterEach(function () {
    Carbon::setTestNow();
});
