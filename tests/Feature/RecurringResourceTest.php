<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Filament\Resources\Recurrings\Pages\CreateRecurring;
use App\Filament\Resources\Recurrings\Pages\ListRecurrings;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('primary can open recurring index', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $this->get(RecurringResource::getUrl('index'))
        ->assertOk();
});

test('family member cannot access recurring resource', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();

    $this->actingAs($member->loginUser);

    $this->get(RecurringResource::getUrl('index'))
        ->assertRedirect(route('filament.admin.auth.forbidden'));
});

test('primary can create recurring from form', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $label = Label::factory()->create();

    Livewire::test(CreateRecurring::class)
        ->fillForm([
            'title' => 'Cursor Pro',
            'type' => RecurringType::Subscription->value,
            'label_id' => $label->id,
            'expected_amount' => 84.79,
            'cadence_preset' => 'monthly',
            'frequency' => RecurringFrequency::Repeating->value,
            'interval_months' => 1,
            'anchor_day' => 8,
            'starts_on' => now()->toDateString(),
            'merchant_aliases' => ['Cursor', 'Anysphere'],
            'notify_filament' => true,
            'notify_whatsapp' => true,
            'is_active' => true,
            'is_shared' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Recurring::query()->where('title', 'Cursor Pro')->exists())->toBeTrue();
});

test('list page renders for primary', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    Recurring::factory()->create(['title' => 'GPROP']);

    Livewire::test(ListRecurrings::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Recurring::all());
});
