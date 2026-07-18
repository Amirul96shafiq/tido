<?php

declare(strict_types=1);

use App\Filament\Forms\Components\NotesRichEditor;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Models\Budget;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('create budget page saves new fields', function () {
    $label = Label::factory()->create(['name' => 'Pet Supplies']);

    Livewire::test(CreateBudget::class)
        ->fillForm([
            'title' => 'Pets 2026',
            'icon' => 'heroicon-o-heart',
            'label_id' => $label->id,
            'amount' => 200.00,
            'period' => 'monthly',
            'year' => (int) now()->year,
            'alert_threshold' => 80,
            'critical_threshold' => 100,
            'notify_filament' => true,
            'notify_whatsapp' => false,
            'is_active' => true,
            'notes' => '<p>Includes litter and food</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $budget = Budget::query()->first();

    expect($budget)->not->toBeNull()
        ->and($budget->title)->toBe('Pets 2026')
        ->and($budget->icon)->toBe('heroicon-o-heart')
        ->and($budget->notes)->toBe('<p>Includes litter and food</p>')
        ->and($budget->critical_threshold)->toBe(100)
        ->and($budget->notify_whatsapp)->toBeFalse()
        ->and($budget->display_title)->toBe('Pets 2026')
        ->and($budget->display_icon)->toBe('heroicon-o-heart');
});

test('budget form uses rich editor for notes', function () {
    Livewire::test(CreateBudget::class)
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'notes',
            checkComponentUsing: function (NotesRichEditor $component): bool {
                expect($component->getExtraAttributes())->toMatchArray([
                    'class' => NotesRichEditor::EXTRA_CLASS,
                ]);

                return true;
            },
        );
});

test('edit budget page shows performance section', function () {
    $budget = Budget::factory()->create([
        'title' => 'Groceries Cap',
        'amount' => 500.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
    ]);

    $periodLabel = $budget->getStartDate()->format('d M Y').' – '.$budget->getEndDate()->format('d M Y');

    Livewire::test(EditBudget::class, ['record' => $budget->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Budget Performance')
        ->assertSee('Budget Appearance')
        ->assertSee('Budget Settings')
        ->assertSee('Groceries Cap')
        ->assertSee('Spent')
        ->assertSee('Limit')
        ->assertSee('Remaining')
        ->assertSee('On track')
        ->assertSee($periodLabel)
        ->assertSee('fi-budget-form-page', false)
        ->assertSee('fi-budget-sidebar-sticky', false);
});

test('critical threshold cannot be below warn threshold', function () {
    Livewire::test(CreateBudget::class)
        ->fillForm([
            'amount' => 100.00,
            'period' => 'monthly',
            'year' => (int) now()->year,
            'alert_threshold' => 90,
            'critical_threshold' => 70,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['critical_threshold']);
});

test('selecting a label auto-fills empty title and icon', function () {
    $label = Label::factory()->create([
        'name' => 'Pet Supplies',
        'icon' => 'heroicon-o-heart',
    ]);

    Livewire::test(CreateBudget::class)
        ->fillForm([
            'title' => null,
            'icon' => null,
            'label_id' => $label->id,
            'amount' => 200.00,
            'period' => 'monthly',
            'year' => (int) now()->year,
            'is_active' => true,
        ])
        ->assertFormSet([
            'title' => 'Pet Supplies',
            'icon' => 'heroicon-o-heart',
        ]);
});

test('selecting a label does not overwrite existing title and icon', function () {
    $label = Label::factory()->create([
        'name' => 'Pet Supplies',
        'icon' => 'heroicon-o-heart',
    ]);

    Livewire::test(CreateBudget::class)
        ->fillForm([
            'title' => 'Custom Pets Cap',
            'icon' => 'heroicon-o-shopping-cart',
            'label_id' => $label->id,
            'amount' => 200.00,
            'period' => 'monthly',
            'year' => (int) now()->year,
            'is_active' => true,
        ])
        ->assertFormSet([
            'title' => 'Custom Pets Cap',
            'icon' => 'heroicon-o-shopping-cart',
        ]);
});

test('threshold sliders store whole percentages', function () {
    Livewire::test(CreateBudget::class)
        ->fillForm([
            'amount' => 100.00,
            'period' => 'monthly',
            'year' => (int) now()->year,
            'alert_threshold' => 74.99999999999999,
            'critical_threshold' => 99.99999999999999,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $budget = Budget::query()->latest('id')->first();

    expect($budget)->not->toBeNull()
        ->and($budget->alert_threshold)->toBe(75)
        ->and($budget->critical_threshold)->toBe(100);
});
