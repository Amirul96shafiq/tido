<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Models\Budget;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('list page renders label badge, quarter placeholder, and overall default', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $label = Label::factory()->create(['name' => 'Food & Dining']);
    $labelledBudget = Budget::factory()->create([
        'title' => 'Groceries',
        'label_id' => $label->id,
        'period' => 'monthly',
        'quarter' => null,
    ]);
    $overallBudget = Budget::factory()->create([
        'title' => 'Overall',
        'label_id' => null,
        'period' => 'quarterly',
        'quarter' => 2,
    ]);

    Livewire::test(ListBudgets::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Budget::all())
        ->assertTableColumnExists('label.name', function (TextColumn $column): bool {
            return $column->isBadge()
                && $column->getPlaceholder() === 'None'
                && $column->getDefaultState() === 'Overall (All Labels)';
        })
        ->assertTableColumnStateSet('label.name', 'Food & Dining', $labelledBudget)
        ->assertTableColumnStateSet('label.name', 'Overall (All Labels)', $overallBudget)
        ->assertTableColumnExists('quarter', function (TextColumn $column): bool {
            return $column->getPlaceholder() === '—';
        })
        ->assertTableColumnFormattedStateSet('quarter', 'Q2', $overallBudget)
        ->assertTableColumnExists('period', function (TextColumn $column): bool {
            return $column->isBadge();
        })
        ->assertTableColumnExists('editedBy.name', function (TextColumn $column): bool {
            return $column->getPlaceholder() === 'System';
        });
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

    $primaryBudget = Budget::factory()->create([
        'title' => 'Primary Budget',
        'family_member_id' => null,
        'period' => 'monthly',
        'quarter' => null,
    ]);

    $familyBudget = Budget::factory()->forFamilyMember($member)->create([
        'title' => 'Family Budget',
        'period' => 'monthly',
        'quarter' => null,
    ]);

    Livewire::test(ListBudgets::class)
        ->assertOk()
        ->assertSee('Assigned to')
        ->assertTableColumnStateSet('assigned_to', 'admin', $primaryBudget)
        ->assertTableColumnStateSet('assigned_to', 'nor', $familyBudget)
        ->assertTableColumnFormattedStateSet('editedBy.name', 'admin', $primaryBudget)
        ->assertDontSee('Nor Ezrieana Harun');
});

test('primary can duplicate a budget from the list', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $label = Label::factory()->create(['name' => 'Food & Dining']);
    $source = Budget::factory()->create([
        'title' => 'Groceries',
        'label_id' => $label->id,
        'amount' => 800.00,
        'period' => 'monthly',
        'year' => 2025,
        'quarter' => null,
        'alert_threshold' => 70,
        'is_shared' => false,
        'notes' => '<p>Weekly shop</p>',
    ]);

    $page = Livewire::test(ListBudgets::class)
        ->callAction(TestAction::make('replicate')->table($source))
        ->assertNotified('Budget duplicated');

    $replica = Budget::query()
        ->where('title', 'Groceries')
        ->whereKeyNot($source->id)
        ->first();

    expect($replica)->not->toBeNull();

    $page->assertRedirect(BudgetResource::getUrl('edit', ['record' => $replica]));

    expect((float) $replica->amount)->toBe(800.00)
        ->and($replica->label_id)->toBe($label->id)
        ->and($replica->period)->toBe('monthly')
        ->and($replica->year)->toBe(2025)
        ->and($replica->alert_threshold)->toBe(70)
        ->and($replica->notes)->toBe('<p>Weekly shop</p>')
        ->and($replica->sort_order)->not->toBeNull()
        ->and($replica->sort_order)->not->toBe($source->sort_order);
});

test('primary can bulk duplicate budgets from the list', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $first = Budget::factory()->create(['title' => 'Food']);
    $second = Budget::factory()->create(['title' => 'Transport']);

    expect(Budget::query()->count())->toBe(2);

    Livewire::test(ListBudgets::class)
        ->selectTableRecords([$first->getKey(), $second->getKey()])
        ->callAction(TestAction::make('duplicate')->table()->bulk())
        ->assertNotified('2 budgets duplicated')
        ->assertNoRedirect();

    expect(Budget::query()->count())->toBe(4)
        ->and(Budget::query()->where('title', 'Food')->count())->toBe(2)
        ->and(Budget::query()->where('title', 'Transport')->count())->toBe(2);
});

test('budgets table supports deleted records filter and soft delete actions', function () {
    $this->actingAs(User::factory()->create([
        'household_role' => HouseholdRole::Primary,
    ]));

    $active = Budget::factory()->create(['title' => 'Active Budget']);
    $trashed = Budget::factory()->create(['title' => 'Trashed Budget']);
    $trashed->delete();

    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$trashed])
        ->assertTableFilterExists('trashed', fn ($filter): bool => $filter instanceof TrashedFilter)
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$active, $trashed])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed])
        ->assertCanNotSeeTableRecords([$active]);

    Livewire::test(EditBudget::class, ['record' => $trashed->getRouteKey()])
        ->assertSuccessful()
        ->assertActionExists('restore')
        ->assertActionExists('forceDelete');
});
