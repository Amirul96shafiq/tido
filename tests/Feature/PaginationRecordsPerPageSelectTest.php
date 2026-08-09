<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());

    Expense::unsetEventDispatcher();
});

test('invoice list pagination per page uses filament searchable select', function () {
    Expense::factory()->count(12)->create();

    $html = Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->html();

    expect($html)
        ->toContain('fi-pagination-records-per-page-select')
        ->toContain('selectFormComponent')
        ->toContain('isSearchable: true')
        ->toContain('hasDynamicSearchResults: false')
        ->toContain('fi-select-input')
        ->toContain(__('filament-forms::components.select.search_prompt'));

    expect(preg_match(
        '/fi-pagination-records-per-page-select[\s\S]*?<select\b/',
        $html,
    ))->toBe(0);
});

test('invoice list can change records per page via livewire property', function () {
    Expense::factory()->count(30)->create();

    $component = Livewire::test(ListExpenses::class)
        ->assertSuccessful();

    expect($component->instance()->getTableRecords()->count())->toBe(10);

    $component
        ->set('tableRecordsPerPage', '25')
        ->assertSet('tableRecordsPerPage', '25');

    expect($component->instance()->getTableRecords()->count())->toBe(25);
});
