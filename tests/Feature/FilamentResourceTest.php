<?php

declare(strict_types=1);

use App\Enums\LabelingType;
use App\Filament\Forms\Components\IconPicker;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Labelings\LabelingResource;
use App\Filament\Resources\Labelings\Pages\CreateLabeling;
use App\Filament\Resources\Labelings\Pages\EditLabeling;
use App\Filament\Resources\Labelings\Pages\ListLabelings;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\Labeling;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->admin = User::factory()->withWhatsAppPhone('60123456789')->create();
});

test('filament admin page requires authentication', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

test('authenticated user can access dashboard', function () {
    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertSuccessful();
});

test('admin panel has breadcrumbs disabled', function () {
    expect(filament()->getCurrentOrDefaultPanel()->hasBreadcrumbs())->toBeFalse();
});

test('authenticated user can load labelings list', function () {
    expect(LabelingResource::getUrl('index'))->toEndWith('/admin/labels');

    $this->actingAs($this->admin)
        ->get(LabelingResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load invoices list', function () {
    $this->actingAs($this->admin)
        ->get(InvoiceResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load budgets list', function () {
    $this->actingAs($this->admin)
        ->get(BudgetResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load upload page', function () {
    expect(ReceiptUploadPage::getUrl())->toContain('/upload-receipts');

    $this->actingAs($this->admin)
        ->get(ReceiptUploadPage::getUrl())
        ->assertSuccessful();
});

test('invoices table has view slide-over action', function () {
    $this->actingAs($this->admin);

    $invoice = Invoice::factory()->create();

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('view')->table($invoice));
});

test('budgets table has view slide-over action', function () {
    $this->actingAs($this->admin);

    $budget = Budget::factory()->create();

    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('view')->table($budget));
});

test('labelings table has view slide-over action', function () {
    $this->actingAs($this->admin);

    $labeling = Labeling::factory()->create();

    Livewire::test(ListLabelings::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('view')->table($labeling));
});

test('labeling form exposes searchable heroicon options', function () {
    $options = IconPicker::iconOptions();

    expect($options)
        ->toHaveKey('heroicon-o-cake')
        ->and($options['heroicon-o-cake'])->toBe('Cake')
        ->and(count($options))->toBeGreaterThan(100);
});

test('labeling icon options are paginated with search across all icons', function () {
    $page = IconPicker::iconOptionsPage(IconPicker::PAGE_SIZE);
    $all = IconPicker::iconOptions();

    expect($page)
        ->toHaveCount(IconPicker::PAGE_SIZE)
        ->and(count($all))->toBeGreaterThan(IconPicker::PAGE_SIZE);

    $search = IconPicker::searchIconOptions('wallet');

    expect($search)
        ->toHaveKey('heroicon-o-wallet')
        ->and(IconPicker::iconOptionLabel('heroicon-o-cake'))->toBe('Cake');
});

test('labeling create form uses modal icon picker', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateLabeling::class)
        ->assertSuccessful()
        ->assertSee('Choose icon')
        ->assertSee('Quick picks')
        ->assertSee('Load more')
        ->fillForm([
            'icon' => 'heroicon-o-wallet',
        ])
        ->assertFormSet([
            'icon' => 'heroicon-o-wallet',
        ]);
});

test('authenticated user can load labeling create and edit forms', function () {
    $this->actingAs($this->admin);

    $labeling = Labeling::factory()->create([
        'icon' => 'heroicon-o-cake',
        'color' => '#dbb051',
    ]);

    Livewire::test(CreateLabeling::class)
        ->assertSuccessful()
        ->assertFormFieldExists('type')
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('slug')
        ->assertFormFieldExists('icon')
        ->assertFormFieldExists('color')
        ->assertSee(LabelingResource::getTitleCaseModelLabel().' Details')
        ->assertSee(LabelingResource::getTitleCaseModelLabel().' Appearance');

    Livewire::test(EditLabeling::class, ['record' => $labeling->getRouteKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'type' => $labeling->type instanceof LabelingType
                ? $labeling->type->value
                : $labeling->type,
            'name' => $labeling->name,
            'slug' => $labeling->slug,
            'icon' => 'heroicon-o-cake',
            'color' => '#dbb051',
        ]);
});
