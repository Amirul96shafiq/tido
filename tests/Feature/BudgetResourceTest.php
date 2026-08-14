<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Models\Budget;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
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
