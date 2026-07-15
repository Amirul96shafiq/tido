<?php

declare(strict_types=1);

use App\Enums\LabelType;
use App\Filament\Forms\Components\IconPicker;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Filament\Resources\Labels\Pages\ListLabels;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Icons\Heroicon;
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

test('authenticated user can load labels list', function () {
    expect(LabelResource::getUrl('index'))->toEndWith('/admin/labels');

    $this->actingAs($this->admin)
        ->get(LabelResource::getUrl('index'))
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

test('labels table has view slide-over action', function () {
    $this->actingAs($this->admin);

    $label = Label::factory()->create();

    Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('view')->table($label));
});

test('resource table record actions are icon-only', function () {
    $this->actingAs($this->admin);

    Label::factory()->create();

    $table = Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    foreach (['view', 'edit', 'delete'] as $actionName) {
        $action = $table->getAction($actionName);

        expect($action)->not->toBeNull()
            ->and($action->isIconButton())->toBeTrue();
    }
});

test('resource table icon actions use filament tooltips', function () {
    $this->actingAs($this->admin);

    Label::factory()->create();

    $table = Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    foreach (['view', 'edit', 'delete'] as $actionName) {
        $action = $table->getAction($actionName);

        expect($action)->not->toBeNull()
            ->and($action->getTooltip())->toBe($action->getLabel());
    }

    $filtersTrigger = $table->getFiltersTriggerAction();

    expect($filtersTrigger->getTooltip())->toBe($filtersTrigger->getLabel());

    $columnManagerTrigger = $table->getColumnManagerTriggerAction();

    expect($columnManagerTrigger->getTooltip())->toBe($columnManagerTrigger->getLabel());
});

test('resource list create actions have plus icon', function () {
    $this->actingAs($this->admin);

    foreach ([ListLabels::class, ListBudgets::class, ListInvoices::class] as $page) {
        Livewire::test($page)
            ->assertSuccessful()
            ->assertActionHasIcon('create', Heroicon::Plus);
    }
});

test('resource tables show created_at as relative time with datetime tooltip', function () {
    $this->actingAs($this->admin);

    $createdAt = now()->subHours(3);
    $relative = $createdAt->diffForHumans();

    $label = Label::factory()->create(['created_at' => $createdAt]);
    $budget = Budget::factory()->create(['created_at' => $createdAt]);
    $invoice = Invoice::factory()->create(['created_at' => $createdAt]);

    Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$label])
        ->assertSee($relative);

    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$budget])
        ->assertSee($relative);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->toggleAllTableColumns()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee($relative);

    foreach ([ListLabels::class, ListBudgets::class, ListInvoices::class] as $page) {
        $column = Livewire::test($page)
            ->instance()
            ->getTable()
            ->getColumn('created_at');

        expect($column)->not->toBeNull();

        $tooltip = $column->record(match ($page) {
            ListLabels::class => $label,
            ListBudgets::class => $budget,
            default => $invoice,
        })->getTooltip($createdAt);

        expect($tooltip)->toBeString()->not->toBeEmpty()
            ->and($tooltip)->not->toBe($relative);
    }
});

test('invoices table truncates long merchant names with full name in tooltip', function () {
    $this->actingAs($this->admin);

    $longMerchant = 'Cosmo Restaurants Sdn Bhd';
    $invoice = Invoice::factory()->create([
        'merchant_name' => $longMerchant,
    ]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('Cosmo Restaurants Sd...');

    $column = Livewire::test(ListInvoices::class)
        ->instance()
        ->getTable()
        ->getColumn('merchant_name');

    expect($column)->not->toBeNull()
        ->and($column->getCharacterLimit())->toBe(20);

    $tooltip = $column->record($invoice)->getTooltip($longMerchant);

    expect($tooltip)->toBe($longMerchant);
});

test('invoices table leaves short merchant names unchanged', function () {
    $this->actingAs($this->admin);

    $shortMerchant = '7-Eleven';
    $invoice = Invoice::factory()->create([
        'merchant_name' => $shortMerchant,
    ]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee($shortMerchant);

    $column = Livewire::test(ListInvoices::class)
        ->instance()
        ->getTable()
        ->getColumn('merchant_name');

    $tooltip = $column->record($invoice)->getTooltip($shortMerchant);

    expect($tooltip)->toBeNull();
});

test('invoices table shows date_time as relative time with datetime tooltip', function () {
    $this->actingAs($this->admin);

    $dateTime = now()->subDays(2)->seconds(0);
    $relative = $dateTime->diffForHumans();

    $invoice = Invoice::factory()->create([
        'date_time' => $dateTime,
        'created_at' => now()->subMinutes(5),
    ]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee($relative);

    $column = Livewire::test(ListInvoices::class)
        ->instance()
        ->getTable()
        ->getColumn('date_time');

    expect($column)->not->toBeNull();

    $tooltip = $column->record($invoice)->getTooltip($dateTime);

    expect($tooltip)->toBeString()->not->toBeEmpty()
        ->and($tooltip)->not->toBe($relative);
});

test('labels table renders icon as graphic not name', function () {
    $this->actingAs($this->admin);

    $label = Label::factory()->create([
        'icon' => 'heroicon-o-cake',
        'name' => 'Dessert Label',
    ]);

    Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$label])
        ->assertDontSee('heroicon-o-cake')
        ->assertSeeHtml('<svg');
});

test('label form exposes searchable heroicon options', function () {
    $options = IconPicker::iconOptions();

    expect($options)
        ->toHaveKey('heroicon-o-cake')
        ->and($options['heroicon-o-cake'])->toBe('Cake')
        ->and(count($options))->toBeGreaterThan(100);
});

test('label icon options are paginated with search across all icons', function () {
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

test('label create form uses modal icon picker', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateLabel::class)
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

test('authenticated user can load label create and edit forms', function () {
    $this->actingAs($this->admin);

    $label = Label::factory()->create([
        'icon' => 'heroicon-o-cake',
        'color' => '#dbb051',
    ]);

    Livewire::test(CreateLabel::class)
        ->assertSuccessful()
        ->assertFormFieldExists('type')
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('slug')
        ->assertFormFieldExists('icon')
        ->assertFormFieldExists('color')
        ->assertSee(LabelResource::getTitleCaseModelLabel().' Details')
        ->assertSee(LabelResource::getTitleCaseModelLabel().' Appearance');

    Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'type' => $label->type instanceof LabelType
                ? $label->type->value
                : $label->type,
            'name' => $label->name,
            'slug' => $label->slug,
            'icon' => 'heroicon-o-cake',
            'color' => '#dbb051',
        ]);
});
