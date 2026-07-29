<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Lightweight load probe for Dashboard Finances view.
 * Asserts the page succeeds and records query count for before/after comparisons.
 */
test('dashboard finances load completes within a sane query budget', function () {
    $this->actingAs(User::factory()->create());

    $queryCount = 0;

    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $startedAt = hrtime(true);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful();

    $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;

    expect($queryCount)->toBeGreaterThan(0)
        ->and($queryCount)->toBeLessThan(200)
        ->and($elapsedMs)->toBeLessThan(15_000);
});
