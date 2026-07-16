<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);
});

function resetMonthAction(): TestAction
{
    return TestAction::make('resetMonth')
        ->schemaComponent('resetMonthActions', schema: 'filtersForm');
}

test('dashboard shows reset month action beside month filter', function () {
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
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-scope', false);
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
