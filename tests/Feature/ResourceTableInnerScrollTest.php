<?php

declare(strict_types=1);

use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\FamilyMembers\Pages\ListFamilyMembers;
use App\Filament\Resources\Labels\Pages\ListLabels;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('app.css contains resource table inner scroll and height rules', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.fi-ta-ctn')
        ->toContain('.fi-ta-content-ctn')
        ->toContain('max-height: 65vh;')
        ->toContain('overflow-y: auto !important;');
});

test('app.css freezes resource table record actions on the right', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.fi-ta-actions-header-cell')
        ->toContain(':has(.fi-ta-actions)')
        ->toContain('inset-inline-end: 0;')
        ->toContain('::before')
        ->toContain('z-index: 30;');
});

test('resource list pages render table containers properly', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListExpenses::class)->assertSuccessful();
    Livewire::test(ListLabels::class)->assertSuccessful();
    Livewire::test(ListBudgets::class)->assertSuccessful();
    Livewire::test(ListPaymentMethods::class)->assertSuccessful();
    Livewire::test(ListFamilyMembers::class)->assertSuccessful();
    Livewire::test(ListBackups::class)->assertSuccessful();
});

test('expenses list renders sticky record actions with teleported kebab', function () {
    $this->actingAs($this->admin);

    Expense::factory()->create();

    $html = Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->html();

    expect($html)
        ->toContain('fi-ta-actions')
        ->toContain('fi-ta-actions-header-cell')
        ->toContain('.teleport');
});
