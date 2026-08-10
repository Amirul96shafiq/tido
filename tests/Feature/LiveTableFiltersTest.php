<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->withWhatsAppPhone('60123456789')->create();
    $this->actingAs($this->admin);
});

test('table filters and column manager apply live without deferred apply action', function () {
    $pending = Expense::factory()->create(['status' => 'pending']);
    $parsed = Expense::factory()->create(['status' => 'parsed']);

    $component = Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertSeeHtml('wire:poll.10s.visible')
        ->assertCanSeeTableRecords([$pending, $parsed]);

    expect($component->instance()->getTable()->hasDeferredFilters())->toBeFalse()
        ->and($component->instance()->getTable()->hasDeferredColumnManager())->toBeFalse();

    $html = $component->html();

    expect($html)
        ->toContain('resetTableFiltersForm')
        ->toContain('resetTableColumnManager')
        ->toContain('aria-label="Reset"')
        ->toContain('fi-ta-filters-dropdown')
        ->toContain('fi-ta-col-manager-dropdown')
        ->toContain('max-height: min(40vh, 20rem)')
        ->toContain('fi-scrollable')
        ->toContain('isLive: true')
        ->not->toContain(__('filament-tables::table.filters.actions.apply.label'))
        ->not->toContain(__('filament-tables::table.column_manager.actions.apply.label'))
        ->not->toContain('fi-ta-filters-heading');

    expect($component->instance()->getTable()->getFiltersFormMaxHeight())->toBe('min(40vh, 20rem)')
        ->and($component->instance()->getTable()->getColumnManagerMaxHeight())->toBe('min(40vh, 20rem)');

    $component
        ->filterTable('status', 'pending')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$parsed])
        ->call('resetTableFiltersForm')
        ->assertCanSeeTableRecords([$pending, $parsed]);
});
