<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Filament\Pages\Dashboard;
use App\Models\FamilyMember;
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
        ->assertSee('fi-fixed-positioning-context', false)
        ->assertSee('aria-label="Filters"', false)
        ->assertSee('max-height: min(40vh, 20rem)', false)
        ->assertSee('wire:ignore.self', false)
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

test('finance dashboard defaults from filter to the current primary user', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSet('filters.spender', DashboardSpenderScope::PRIMARY);
});

test('finance dashboard defaults from filter to the current family member', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $member = FamilyMember::factory()->create(['name' => 'Alpha']);
    $user = User::factory()
        ->familyMember($member->id)
        ->create([
            'household_role' => HouseholdRole::FamilyMember,
            'timezone' => 'Asia/Kuala_Lumpur',
            'phone' => '60115554444',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSet('filters.spender', DashboardSpenderScope::familyValue((int) $member->id));
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
        ->set('filters.spender', DashboardSpenderScope::ALL)
        ->assertSet('filters.spender', DashboardSpenderScope::ALL);
});

test('reset filters action restores current month', function () {
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

test('reset filters action resets spender to default', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->set('filters.spender', DashboardSpenderScope::ALL)
        ->set('filters.month', '2026-05')
        ->callAction(resetMonthAction())
        ->assertSet('filters.month', '2026-07')
        ->assertSet('filters.spender', DashboardSpenderScope::PRIMARY);
});

test('reset filters action is enabled when spender is not default even on current month', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->set('filters.spender', DashboardSpenderScope::ALL)
        ->assertSet('filters.month', '2026-07')
        ->assertActionEnabled(resetMonthAction())
        ->callAction(resetMonthAction())
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
        ->set('filters.spender', DashboardSpenderScope::ALL)
        ->set('filters.month', '2026-07')
        ->callAction(previousMonthAction())
        ->assertSet('filters.month', '2026-06')
        ->assertSet('filters.spender', DashboardSpenderScope::ALL);
});

test('reset filters action is disabled when month and spender are defaults', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSet('filters.month', '2026-07')
        ->assertSet('filters.spender', DashboardSpenderScope::PRIMARY)
        ->assertActionDisabled(resetMonthAction());
});

afterEach(function () {
    Carbon::setTestNow();
});
