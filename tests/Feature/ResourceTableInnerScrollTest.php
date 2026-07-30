<?php

declare(strict_types=1);

use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\FamilyMembers\Pages\ListFamilyMembers;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Labels\Pages\ListLabels;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
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

test('resource list pages render table containers properly', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListInvoices::class)->assertSuccessful();
    Livewire::test(ListLabels::class)->assertSuccessful();
    Livewire::test(ListBudgets::class)->assertSuccessful();
    Livewire::test(ListPaymentMethods::class)->assertSuccessful();
    Livewire::test(ListFamilyMembers::class)->assertSuccessful();
    Livewire::test(ListBackups::class)->assertSuccessful();
});
