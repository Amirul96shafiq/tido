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

test('table filters apply live without deferred apply action', function () {
    $pending = Expense::factory()->create(['status' => 'pending']);
    $parsed = Expense::factory()->create(['status' => 'parsed']);

    $component = Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertSeeHtml('wire:poll.10s.visible')
        ->assertCanSeeTableRecords([$pending, $parsed]);

    expect($component->instance()->getTable()->hasDeferredFilters())->toBeFalse();

    $html = $component->html();

    expect($html)
        ->toContain('resetTableFiltersForm')
        ->toContain('aria-label="Reset"')
        ->not->toContain(__('filament-tables::table.filters.actions.apply.label'))
        ->not->toContain('fi-ta-filters-heading');

    $component
        ->filterTable('status', 'pending')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$parsed])
        ->call('resetTableFiltersForm')
        ->assertCanSeeTableRecords([$pending, $parsed]);
});
