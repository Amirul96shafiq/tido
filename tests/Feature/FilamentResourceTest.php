<?php

declare(strict_types=1);

use App\Enums\LabelType;
use App\Filament\Forms\Components\IconPicker;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\FamilyMembers\Pages\ListFamilyMembers;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Filament\Resources\Labels\Pages\ListLabels;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Helpers\FilenameDisplay;
use App\Models\Backup;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

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

test('admin panel has breadcrumbs enabled', function () {
    expect(filament()->getCurrentOrDefaultPanel()->hasBreadcrumbs())->toBeTrue();
});

test('authenticated user can load labels list', function () {
    expect(LabelResource::getUrl('index'))->toEndWith('/admin/labels');

    $this->actingAs($this->admin)
        ->get(LabelResource::getUrl('index'))
        ->assertSuccessful();
});

test('family members see primary-only resource navigation as restricted', function () {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('Labels', false)
        ->assertSee('tido-primary-only-navigation', false)
        ->assertSee('tido-primary-only-navigation-lock', false)
        ->assertSee('Only the Primary member can access this page.', false)
        ->assertSeeHtml('aria-disabled="true"')
        ->assertDontSeeHtml('href="'.e(LabelResource::getUrl('index')).'"');

    $this->get(LabelResource::getUrl('index'))
        ->assertForbidden();

    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('.tido-primary-only-navigation')
        ->toContain('opacity: 0.5;');
});

test('authenticated user can load expenses list', function () {
    $this->actingAs($this->admin)
        ->get(ExpenseResource::getUrl('index'))
        ->assertSuccessful();
});

test('expenses list query eager loads table relations', function () {
    expect(array_keys(ExpenseResource::getEloquentQuery()->getEagerLoads()))
        ->toContain('paymentMethod', 'familyMember', 'editedBy');
});

test('expenses table supports deleted records filter and soft delete actions', function () {
    $this->actingAs($this->admin);

    Expense::unsetEventDispatcher();

    $active = Expense::factory()->create(['merchant_name' => 'Active Merchant']);
    $trashed = Expense::factory()->create(['merchant_name' => 'Trashed Merchant']);
    $trashed->delete();

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$trashed])
        ->assertTableFilterExists('trashed', fn ($filter): bool => $filter instanceof TrashedFilter)
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$active, $trashed])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed])
        ->assertCanNotSeeTableRecords([$active]);

    Livewire::test(EditExpense::class, ['record' => $trashed->getRouteKey()])
        ->assertSuccessful()
        ->assertActionExists('restore')
        ->assertActionExists('forceDelete');
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

test('expenses table has view slide-over action', function () {
    $this->actingAs($this->admin);

    $expense = Expense::factory()->create();

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('view')->table($expense));
});

test('resource tables show id column', function (string $pageClass) {
    $this->actingAs($this->admin);

    match ($pageClass) {
        ListExpenses::class => Expense::factory()->create(),
        ListLabels::class => Label::factory()->create(),
        ListBudgets::class => Budget::factory()->create(),
        ListPaymentMethods::class => PaymentMethod::factory()->create(),
        ListFamilyMembers::class => FamilyMember::factory()->create(),
        ListBackups::class => Backup::factory()->create(),
    };

    Livewire::test($pageClass)
        ->assertSuccessful()
        ->toggleAllTableColumns()
        ->assertCanRenderTableColumn('id')
        ->assertCanRenderTableColumn('editedBy.name')
        ->assertCanRenderTableColumn('updated_at');
})->with([
    'expenses' => [ListExpenses::class],
    'labels' => [ListLabels::class],
    'budgets' => [ListBudgets::class],
    'payment methods' => [ListPaymentMethods::class],
    'family members' => [ListFamilyMembers::class],
    'backups' => [ListBackups::class],
]);

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

    $viewAction = $table->getAction('view');

    expect($viewAction)->not->toBeNull()
        ->and($viewAction->isIconButton())->toBeTrue();

    $actionsGroup = collect($table->getRecordActions())
        ->first(fn (mixed $action): bool => $action instanceof ActionGroup);

    expect($actionsGroup)->toBeInstanceOf(ActionGroup::class)
        ->and($actionsGroup->isIconButton())->toBeTrue();
});

test('resource table icon actions use filament tooltips', function () {
    $this->actingAs($this->admin);

    Label::factory()->create();

    $table = Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    $viewAction = $table->getAction('view');

    expect($viewAction)->not->toBeNull()
        ->and($viewAction->getTooltip())->toBe($viewAction->getLabel());

    foreach (['edit', 'delete'] as $actionName) {
        expect($table->getAction($actionName))->not->toBeNull();
    }

    $actionsGroup = collect($table->getRecordActions())
        ->first(fn (mixed $action): bool => $action instanceof ActionGroup);

    expect($actionsGroup)->toBeInstanceOf(ActionGroup::class)
        ->and($actionsGroup->getTooltip())->toBe('Actions')
        ->and($actionsGroup->getLabel())->toBe('Actions');

    $filtersTrigger = $table->getFiltersTriggerAction();

    expect($filtersTrigger->getTooltip())->toBe($filtersTrigger->getLabel());

    $columnManagerTrigger = $table->getColumnManagerTriggerAction();

    expect($columnManagerTrigger->getTooltip())->toBe($columnManagerTrigger->getLabel());
});

test('expenses table keeps reparse action under record actions group', function () {
    $this->actingAs($this->admin);

    Expense::factory()->create();

    $table = Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->instance()
        ->getTable();

    expect($table->getAction('reparse'))->not->toBeNull();

    $actionsGroup = collect($table->getRecordActions())
        ->first(fn (mixed $action): bool => $action instanceof ActionGroup);

    expect($actionsGroup)->toBeInstanceOf(ActionGroup::class)
        ->and(array_key_exists('reparse', $actionsGroup->getFlatActions()))->toBeTrue();
});

test('resource list create actions have plus icon', function () {
    $this->actingAs($this->admin);

    foreach ([ListLabels::class, ListBudgets::class, ListExpenses::class] as $page) {
        Livewire::test($page)
            ->assertSuccessful()
            ->assertActionHasIcon('create', Heroicon::Plus);
    }
});

test('resource tables show updated_at as relative time with datetime tooltip', function () {
    $this->actingAs($this->admin);

    $editedAt = now()->subHours(3);
    $relative = $editedAt->diffForHumans();

    $label = Label::factory()->create(['updated_at' => $editedAt]);
    $budget = Budget::factory()->create(['updated_at' => $editedAt]);
    $expense = Expense::factory()->create(['updated_at' => $editedAt]);

    Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$label])
        ->assertSee($relative);

    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$budget])
        ->assertSee($relative);

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense]);

    foreach ([ListLabels::class, ListBudgets::class, ListExpenses::class] as $page) {
        $column = Livewire::test($page)
            ->instance()
            ->getTable()
            ->getColumn('updated_at');

        expect($column)->not->toBeNull();

        $tooltip = $column->record(match ($page) {
            ListLabels::class => $label,
            ListBudgets::class => $budget,
            default => $expense,
        })->getTooltip($editedAt);

        expect($tooltip)->toBeString()->not->toBeEmpty()
            ->and($tooltip)->not->toBe($relative);
    }
});

test('resource tables show the editor username with name fallback', function () {
    $this->actingAs($this->admin);

    $this->admin->update(['display_name' => 'audit-username']);
    $label = Label::factory()->create();

    Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$label])
        ->assertSee('audit-username')
        ->assertDontSee($this->admin->name);

    $this->admin->update(['display_name' => null]);
    $fallbackLabel = Label::factory()->create();

    Livewire::test(ListLabels::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$fallbackLabel])
        ->assertSee($this->admin->name);
});

test('expenses table truncates long merchant names with full name in tooltip', function () {
    $this->actingAs($this->admin);

    $longMerchant = 'Cosmo Restaurants Sdn Bhd';
    $expense = Expense::factory()->create([
        'merchant_name' => $longMerchant,
    ]);

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->toggleAllTableColumns()
        ->assertCanSeeTableRecords([$expense])
        ->assertSee('Cosmo Restaurants Sd...');

    $column = Livewire::test(ListExpenses::class)
        ->instance()
        ->getTable()
        ->getColumn('merchant_name');

    expect($column)->not->toBeNull()
        ->and($column->getCharacterLimit())->toBe(20);

    $tooltip = $column->record($expense)->getTooltip($longMerchant);

    expect($tooltip)->toBe($longMerchant);
});

test('expenses table leaves short merchant names unchanged', function () {
    $this->actingAs($this->admin);

    $shortMerchant = '7-Eleven';
    $expense = Expense::factory()->create([
        'merchant_name' => $shortMerchant,
    ]);

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense])
        ->assertSee($shortMerchant);

    $column = Livewire::test(ListExpenses::class)
        ->instance()
        ->getTable()
        ->getColumn('merchant_name');

    $tooltip = $column->record($expense)->getTooltip($shortMerchant);

    expect($tooltip)->toBeNull();
});

test('expenses table filename links to file in a new tab', function () {
    $this->actingAs($this->admin);

    Storage::fake();

    $path = 'receipts/expense_receipt.jpg';
    Storage::put($path, 'fake-image-bytes');

    $expense = Expense::factory()->create([
        'original_filename' => 'expense_receipt.jpg',
        'image_path' => $path,
    ]);

    $url = Storage::temporaryUrl($path, now()->addMinutes(30));

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense])
        ->assertCanRenderTableColumn('original_filename')
        ->assertSee('expense_re....jpg')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml(e($url));
});

test('expenses table shows Manual expense plain text without file link', function () {
    $this->actingAs($this->admin);

    $expense = Expense::factory()->create([
        'original_filename' => null,
        'image_path' => null,
    ]);

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense])
        ->assertSee(FilenameDisplay::MANUAL_EXPENSE_LABEL);

    expect(FilenameDisplay::labelForExpense($expense))->toBe('Manual expense')
        ->and($expense->fileUrl())->toBeNull();
});

test('expenses table shows date_time as relative time with datetime tooltip', function () {
    $this->actingAs($this->admin);

    $dateTime = now()->subDays(2)->seconds(0);
    $relative = $dateTime->diffForHumans();

    $expense = Expense::factory()->create([
        'date_time' => $dateTime,
        'created_at' => now()->subMinutes(5),
    ]);

    Livewire::test(ListExpenses::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense]);

    $column = Livewire::test(ListExpenses::class)
        ->instance()
        ->getTable()
        ->getColumn('date_time');

    expect($column)->not->toBeNull();

    $tooltip = $column->record($expense)->getTooltip($dateTime);

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
        ->assertSeeHtml('<svg')
        ->assertSeeHtml('class="fi-sr-only">heroicon-o-cake</span>');
});

test('budgets table renders display icon as graphic not name', function () {
    $this->actingAs($this->admin);

    $budget = Budget::factory()->create([
        'icon' => 'heroicon-o-heart',
        'title' => 'Heart Budget',
    ]);

    Livewire::test(ListBudgets::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$budget])
        ->assertCanRenderTableColumn('display_icon')
        ->assertSeeHtml('<svg')
        ->assertSeeHtml('class="fi-sr-only">heroicon-o-heart</span>');
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
        ->assertSeeHtml('grid-template-columns: repeat('.count(IconPicker::curatedIconValues()).', minmax(0, 1fr));')
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

    $modelLabel = LabelResource::getTitleCaseModelLabel();

    Livewire::test(CreateLabel::class)
        ->assertSuccessful()
        ->assertFormFieldExists('type')
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('slug')
        ->assertFormFieldExists('icon')
        ->assertFormFieldExists('color')
        ->assertSeeInOrder([
            $modelLabel.' Details',
            'Label Notes',
            $modelLabel.' Appearance',
        ]);

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
        ])
        ->assertSeeInOrder([
            $modelLabel.' Details',
            'Label Notes',
            $modelLabel.' Appearance',
        ])
        ->assertActionHidden('forceDelete');
});

test('trashed label edit page exposes restore and force delete actions', function () {
    $this->actingAs($this->admin);

    $label = Label::factory()->create();
    $label->delete();

    Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()])
        ->assertSuccessful()
        ->assertActionExists('restore')
        ->assertActionExists('forceDelete');
});
