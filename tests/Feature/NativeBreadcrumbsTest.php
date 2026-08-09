<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
});

test('create expense page shows native breadcrumbs to index', function () {
    $indexUrl = ExpenseResource::getUrl('index');
    $homeUrl = Dashboard::getUrl();

    Livewire::test(CreateExpense::class)
        ->assertSee('fi-breadcrumbs', false)
        ->assertSee('Home')
        ->assertSee($homeUrl, false)
        ->assertSee($indexUrl, false)
        ->assertDontSee('Go back to table');
});

test('edit expense page shows native breadcrumbs to index', function () {
    $expense = Expense::factory()->create();
    $indexUrl = ExpenseResource::getUrl('index');
    $homeUrl = Dashboard::getUrl();

    Livewire::test(EditExpense::class, ['record' => $expense->getRouteKey()])
        ->assertSee('fi-breadcrumbs', false)
        ->assertSee('Home')
        ->assertSee($homeUrl, false)
        ->assertSee($indexUrl, false)
        ->assertDontSee('Go back to table');
});

test('list expenses page shows home expenses list breadcrumbs', function () {
    $indexUrl = ExpenseResource::getUrl('index');
    $homeUrl = Dashboard::getUrl();

    Livewire::test(ListExpenses::class)
        ->assertSee('fi-breadcrumbs', false)
        ->assertSee('Home')
        ->assertSee($homeUrl, false)
        ->assertSee('Expenses')
        ->assertSee('List')
        ->assertSee($indexUrl, false)
        ->assertDontSee('Go back to table');
});

test('custom receipt upload page shows home and title breadcrumbs', function () {
    $homeUrl = Dashboard::getUrl();

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSee('fi-breadcrumbs', false)
        ->assertSee('Home')
        ->assertSee($homeUrl, false)
        ->assertSee('Upload Receipts');
});

test('app css keeps breadcrumbs visible below the sm breakpoint', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $block = Str::between(
        $css,
        '.fi-header .fi-breadcrumbs {',
        '.fi-header .fi-breadcrumbs .fi-breadcrumbs-item-label {',
    );

    expect($block)
        ->toContain('display: block;')
        ->toContain('min-width: 0;')
        ->and($css)
        ->toContain('.fi-header .fi-breadcrumbs .fi-breadcrumbs-item-label {')
        ->toContain('padding-block: 0.25rem;');
});
