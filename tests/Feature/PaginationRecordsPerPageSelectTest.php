<?php

declare(strict_types=1);

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());

    Invoice::unsetEventDispatcher();
});

test('invoice list pagination per page uses filament searchable select', function () {
    Invoice::factory()->count(12)->create();

    $html = Livewire::test(ListInvoices::class)
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
    Invoice::factory()->count(30)->create();

    $component = Livewire::test(ListInvoices::class)
        ->assertSuccessful();

    expect($component->instance()->getTableRecords()->count())->toBe(10);

    $component
        ->set('tableRecordsPerPage', '25')
        ->assertSet('tableRecordsPerPage', '25');

    expect($component->instance()->getTableRecords()->count())->toBe(25);
});
