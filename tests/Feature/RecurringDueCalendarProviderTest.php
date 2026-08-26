<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\Calendar\RecurringDueCalendarProvider;
use App\Support\Calendar\CalendarModule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recurring due provider maps occurrence to finances calendar event', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $recurring = Recurring::factory()->create(['title' => 'Netflix']);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Overdue,
        'due_on' => '2026-08-10',
        'expected_amount' => 55.00,
    ]);

    $provider = new RecurringDueCalendarProvider;
    $events = $provider->eventsForRange(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
        $user,
    );

    expect($events)->toHaveCount(1)
        ->and($events->first()?->module)->toBe(CalendarModule::Finances)
        ->and($events->first()?->title)->toBe('Netflix')
        ->and($events->first()?->colorKey)->toBe('danger')
        ->and($events->first()?->url)->toBe(RecurringResource::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $recurring->getRouteKey(),
        ]));
});

test('recurring due provider respects household visibility for family member', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $user = $member->loginUser;

    $assigned = Recurring::factory()->create([
        'title' => 'Assigned Bill',
        'family_member_id' => $member->id,
        'is_shared' => false,
    ]);

    $primaryOnly = Recurring::factory()->create([
        'title' => 'Primary Bill',
        'family_member_id' => null,
        'is_shared' => false,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $assigned->id,
        'due_on' => '2026-08-12',
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $primaryOnly->id,
        'due_on' => '2026-08-12',
    ]);

    $provider = new RecurringDueCalendarProvider;
    $events = $provider->eventsForRange(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
        $user,
    );

    expect($events->pluck('title')->all())->toBe(['Assigned Bill']);
});

test('recurring due provider projects future due dates when no occurrence row exists', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    $recurring = Recurring::factory()->create([
        'title' => 'Projected Bill',
        'starts_on' => '2026-10-01',
        'next_due_on' => '2026-10-15',
        'anchor_day' => 15,
        'interval_months' => 1,
    ]);

    $provider = new RecurringDueCalendarProvider;
    $events = $provider->eventsForRange(
        Carbon::parse('2026-10-01'),
        Carbon::parse('2026-10-31'),
        $user,
    );

    expect($events)->toHaveCount(1)
        ->and($events->first()?->title)->toBe('Projected Bill')
        ->and($events->first()?->colorKey)->toBe('scheduled')
        ->and($events->first()?->meta['projected'] ?? false)->toBeTrue()
        ->and($events->first()?->meta['recurring_id'] ?? null)->toBe($recurring->id);
});
