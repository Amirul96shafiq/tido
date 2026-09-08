<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds household_id columns and composite unique indexes', function (): void {
    expect(Schema::hasTable('households'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('expenses', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('labels', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('payment_methods', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('budgets', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('recurrings', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('backups', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('ollama_settings', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('family_members', 'household_id'))->toBeTrue()
        ->and(Schema::hasColumn('evolution_api_connection_logs', 'household_id'))->toBeTrue();

    expect(Household::query()->whereKey(1)->exists())->toBeTrue();

    $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'index'"))
        ->pluck('name')
        ->all();

    expect($indexes)->toContain('expenses_household_receipt_hash_unique')
        ->and($indexes)->toContain('labels_household_type_slug_unique')
        ->and($indexes)->toContain('payment_methods_household_slug_unique')
        ->and($indexes)->toContain('family_members_household_phone_unique');
});

it('keeps user id one as primary of household one after factory create', function (): void {
    $user = User::factory()->create([
        'household_role' => 'primary',
    ]);

    expect($user->household_id)->toBe(1);

    $expense = Expense::factory()->create(['household_id' => 1]);
    expect($expense->fresh()->household_id)->toBe(1);
});
