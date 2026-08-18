<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringType;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Models\Recurring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));
});

test('create recurring page shows due preview empty state until title and due day are set', function () {
    Livewire::test(CreateRecurring::class)
        ->assertSuccessful()
        ->assertSee('Recurring Payment Due Preview')
        ->assertSee('Set a title and due day to preview')
        ->assertSee('#recurring-due-preview', false)
        ->fillForm([
            'title' => 'TIME Internet',
        ])
        ->assertSee('Set a title and due day to preview')
        ->fillForm([
            'title' => 'TIME Internet',
            'cadence_preset' => 'monthly',
            'anchor_day' => min(28, (int) now()->day),
            'starts_on' => now()->toDateString(),
            'expected_amount' => 89.90,
        ])
        ->assertDontSee('Set a title and due day to preview')
        ->assertSee('TIME Internet')
        ->assertDontSee('1 Recurring Payment Due')
        ->assertSee('Monthly')
        ->assertSee('RM 89.90')
        ->assertSee('Skip')
        ->assertDontSee('Manage');
});

test('create recurring due preview uses live form title amount and cadence', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'TIME Internet',
            'type' => RecurringType::Subscription->value,
            'cadence_preset' => 'monthly',
            'anchor_day' => min(28, (int) now()->day),
            'starts_on' => now()->toDateString(),
            'expected_amount' => 89.90,
        ])
        ->assertSee('TIME Internet')
        ->assertSee('RM 89.90')
        ->assertSee('Monthly')
        ->fillForm([
            'title' => 'Unifi Fibre',
            'expected_amount' => 129.00,
            'cadence_preset' => 'quarterly',
        ])
        ->assertSee('Unifi Fibre')
        ->assertSee('RM 129.00')
        ->assertSee('Quarterly')
        ->assertDontSee('TIME Internet')
        ->assertDontSee('RM 89.90');
});

test('create recurring due preview shows shared badge from live ownership', function () {
    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Netflix',
            'cadence_preset' => 'monthly',
            'anchor_day' => min(28, (int) now()->day),
            'starts_on' => now()->toDateString(),
            'expected_amount' => 55.00,
            'responsibility' => 'household_shared',
        ])
        ->assertSee('Netflix')
        ->assertSee('Shared');
});

test('edit recurring page shows due preview matching the widget row', function () {
    $recurring = Recurring::factory()->create([
        'title' => 'TIME Internet',
        'expected_amount' => 89.90,
        'interval_months' => 1,
        'next_due_on' => now()->toDateString(),
    ]);

    $html = (string) Livewire::test(EditRecurring::class, ['record' => $recurring->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Recurring Payment Due Preview')
        ->assertDontSee('1 Recurring Payment Due')
        ->assertSee('TIME Internet')
        ->assertSee('RM 89.90')
        ->assertSee('Monthly')
        ->assertSee('Skip')
        ->assertDontSee('Manage')
        ->html();

    expect($html)
        ->toContain('fi-due-recurrings-preview-inert')
        ->toContain('opacity-50')
        ->toContain('pointer-events-none')
        ->not->toContain('hover:bg-gray-100')
        ->not->toContain('wire:sort="reorderRecurrings"')
        ->not->toContain('wire:sort:handle')
        ->not->toContain("mountAction('confirmSkipOccurrence'");
});

test('edit recurring due preview uses live form amount before save', function () {
    $recurring = Recurring::factory()->create([
        'title' => 'TIME Internet',
        'expected_amount' => 89.90,
        'interval_months' => 1,
        'next_due_on' => now()->toDateString(),
    ]);

    Livewire::test(EditRecurring::class, ['record' => $recurring->getRouteKey()])
        ->assertSee('RM 89.90')
        ->fillForm([
            'expected_amount' => 102.80,
        ])
        ->assertSee('RM 102.80')
        ->assertDontSee('RM 89.90');
});
