<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\Household;
use App\Models\Label;
use App\Models\User;
use App\Services\HouseholdRegistrationService;
use App\Support\CurrentHousehold;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CurrentHousehold::clear();
});

it('registers a new household and primary without joining household one', function (): void {
    $existing = User::factory()->create(['household_id' => 1]);
    Expense::factory()->create(['household_id' => 1]);

    $user = app(HouseholdRegistrationService::class)->register([
        'name' => 'New Primary',
        'email' => 'new-primary@example.com',
        'password' => 'password-password',
    ]);

    expect($user->household_id)->not->toBe(1)
        ->and($user->isPrimary())->toBeTrue()
        ->and(Household::query()->whereKey($user->household_id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($existing->id)->value('household_id'))->toBe(1)
        ->and(Expense::query()->where('household_id', 1)->count())->toBe(1);

    CurrentHousehold::set((int) $user->household_id);

    expect(Label::query()->where('household_id', $user->household_id)->count())->toBeGreaterThan(0);
});
