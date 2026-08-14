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
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create([
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
            'end_rule' => 'ongoing',
            'responsibility' => 'primary',
            'merchant_aliases' => ['Cursor', 'Anysphere'],
            'notify_filament' => true,
            'notify_whatsapp' => true,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recurring = Recurring::query()->where('title', 'Cursor Pro')->first();

    expect($recurring)->not->toBeNull()
        ->and($recurring->frequency)->toBe(RecurringFrequency::Repeating)
        ->and($recurring->interval_months)->toBe(1)
        ->and($recurring->family_member_id)->toBeNull()
        ->and($recurring->is_shared)->toBeFalse();
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

test('list page shows primary username when assigned to primary', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
        'name' => 'Primary Account Owner',
        'display_name' => 'admin',
    ]));

    $member = FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'nor',
    ]);

    $primaryRecurring = Recurring::factory()->create([
        'title' => 'Primary Recurring',
        'family_member_id' => null,
    ]);

    $familyRecurring = Recurring::factory()->forFamilyMember($member)->create([
        'title' => 'Family Recurring',
    ]);

    Livewire::test(ListRecurrings::class)
        ->assertOk()
        ->assertSee('Assigned to')
        ->assertTableColumnStateSet('assigned_to', 'admin', $primaryRecurring)
        ->assertTableColumnStateSet('assigned_to', 'nor', $familyRecurring)
        ->assertDontSee('Nor Ezrieana Harun');
});
