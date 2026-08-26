<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Calendar\BirthdayCalendarProvider;
use App\Support\Calendar\CalendarModule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('birthday provider maps user birthday onto viewed calendar year', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1990-05-15',
        'display_name' => 'May User',
    ]);

    $provider = new BirthdayCalendarProvider;
    $events = $provider->eventsForRange(
        Carbon::parse('2026-05-01'),
        Carbon::parse('2026-05-31'),
        $user,
    );

    expect($events)->toHaveCount(1)
        ->and($events->first()?->module)->toBe(CalendarModule::Household)
        ->and($events->first()?->title)->toBe('May User')
        ->and($events->first()?->date->toDateString())->toBe('2026-05-15')
        ->and($events->first()?->colorKey)->toBe('birthday-self');
});

test('birthday provider includes family member birthdays', function () {
    $user = User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]);

    FamilyMember::factory()->create([
        'name' => 'Family Birthday',
        'display_name' => null,
        'date_of_birth' => '1988-03-20',
    ]);

    $provider = new BirthdayCalendarProvider;
    $events = $provider->eventsForRange(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-03-31'),
        $user,
    );

    expect($events->pluck('title')->all())->toContain('Family Birthday');
});
