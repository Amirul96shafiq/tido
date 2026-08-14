<?php

declare(strict_types=1);

use App\Filament\Widgets\MonthlySpendingOverview;
use App\Filament\Widgets\MonthlyTrend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('expense items and budgets have lookup indexes for dashboard queries', function () {
    expect(Schema::hasIndex('expense_items', 'expense_items_expense_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('expense_items', 'expense_items_label_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('budgets', 'budgets_is_active_index'))->toBeTrue();
});

test('dashboard stats skip polling while charts still poll on the current month', function () {
    $statsInterval = (new ReflectionMethod(MonthlySpendingOverview::class, 'getPollingInterval'))
        ->invoke(Livewire::test(MonthlySpendingOverview::class)->instance());
    $chartInterval = (new ReflectionMethod(MonthlyTrend::class, 'getPollingInterval'))
        ->invoke(Livewire::test(MonthlyTrend::class)->instance());

    expect($statsInterval)->toBeNull()
        ->and($chartInterval)->toBe('30s');
});

test('desktop auth backgrounds are served as webp', function () {
    $lightWebp = public_path('images/auth-bg-l-v5.webp');
    $darkWebp = public_path('images/auth-bg-d-v5.webp');
    $lightPng = public_path('images/auth-bg-l-v5.png');
    $darkPng = public_path('images/auth-bg-d-v5.png');

    expect($lightWebp)->toBeFile()
        ->and($darkWebp)->toBeFile()
        ->and(filesize($lightWebp))->toBeLessThan(filesize($lightPng))
        ->and(filesize($darkWebp))->toBeLessThan(filesize($darkPng));
});
