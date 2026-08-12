<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\EditRecurring;
use App\Filament\Resources\Recurrings\Schemas\RecurringForm;
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

test('recurring create page renders sticky section nav markers', function () {
    Livewire::test(CreateRecurring::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-marker--bottom', false)
        ->assertSee('tido-section-nav', false)
        ->assertSee('fi-recurring-main-column', false)
        ->assertSee('fi-recurring-sidebar-sticky', false);
});

test('recurring edit page renders sticky section nav markers', function () {
    $recurring = Recurring::factory()->create();

    Livewire::test(EditRecurring::class, ['record' => $recurring->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false)
        ->assertSee('fi-recurring-sidebar-sticky', false);
});

test('recurring section nav has stable destinations', function () {
    Livewire::test(CreateRecurring::class)
        ->assertSuccessful()
        ->assertSee('Summary')
        ->assertSee('Ownership')
        ->assertSee('Details')
        ->assertSee('Schedule')
        ->assertSee('Expense matching')
        ->assertSee('Status and Reminders')
        ->assertSee('Notes')
        ->assertSee('#recurring-summary', false)
        ->assertSee('#recurring-ownership', false)
        ->assertSee('#recurring-details', false)
        ->assertSee('#recurring-schedule', false)
        ->assertSee('#recurring-matching', false)
        ->assertSee('#recurring-status-and-reminders', false)
        ->assertSee('#recurring-notes', false)
        ->assertDontSee('Goal &amp; instalments', false)
        ->assertDontSee('#recurring-goal', false)
        ->assertDontSee('#recurring-basics', false);
});

test('recurring section nav items match sectionNavItems helper', function () {
    expect(RecurringForm::sectionNavItems())->toBe([
        ['label' => 'Summary', 'id' => 'recurring-summary'],
        ['label' => 'Ownership', 'id' => 'recurring-ownership'],
        ['label' => 'Details', 'id' => 'recurring-details'],
        ['label' => 'Schedule', 'id' => 'recurring-schedule'],
        ['label' => 'Expense matching', 'id' => 'recurring-matching'],
        ['label' => 'Status and Reminders', 'id' => 'recurring-status-and-reminders'],
        ['label' => 'Notes', 'id' => 'recurring-notes'],
    ]);
});

test('recurring section nav smooth scrolls on tab click', function () {
    Livewire::test(CreateRecurring::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});

test('recurring notes uses hidden label rich editor', function () {
    Livewire::test(CreateRecurring::class)
        ->assertSuccessful()
        ->assertSee('fi-notes-rich-editor', false);
});
