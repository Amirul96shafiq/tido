<?php

declare(strict_types=1);

use App\Filament\GlobalSearch\AdminDestinationSearch;
use App\Filament\GlobalSearch\GlobalSearchCriteria;
use App\Filament\GlobalSearch\GlobalSearchType;
use App\Filament\GlobalSearch\TidoSearchEngine;
use App\Filament\Livewire\GlobalSearchModal;
use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\User;
use CharrafiMed\GlobalSearchModal\GlobalSearchResults;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    GlobalSearchCriteria::reset();

    $this->actingAs(User::factory()->create());

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

afterEach(function () {
    GlobalSearchCriteria::reset();
});

function applyGlobalSearchCriteria(GlobalSearchType $type, string $sort = 'default', array $filters = []): void
{
    GlobalSearchCriteria::apply($type, $sort, $filters);
}

test('global search modal toolbar renders type sort and filters icon controls', function () {
    $html = Livewire::test(GlobalSearchModal::class)
        ->set('search', 'market')
        ->set('type', 'expenses')
        ->html();

    expect($html)
        ->toContain('fi-gsm-toolbar')
        ->toContain('fi-gsm-toolbar-menu')
        ->toContain('aria-label="Type"')
        ->toContain('aria-label="Sort"')
        ->toContain('aria-label="Filters"')
        ->toContain('fi-gsm-toolbar-filters')
        ->toContain('offset: 12')
        ->toContain('appendTo: () => document.body')
        ->toContain("placement: 'right'")
        ->toContain("placement: 'bottom'")
        ->toContain('zIndex: 100000')
        ->toContain('data-gsm-tooltip-trigger')
        ->not->toMatch('/content:\s*@js\(/')
        ->not->toContain('fi-width-sm')
        ->toContain('Expenses')
        ->toContain('Last Updated');
});

test('global search modal toolbar hides filters control for all type', function () {
    $html = Livewire::test(GlobalSearchModal::class)
        ->set('search', 'market')
        ->set('type', 'all')
        ->html();

    expect($html)
        ->toContain('aria-label="Type"')
        ->toContain('aria-label="Sort"')
        ->not->toContain('fi-gsm-toolbar-filters');
});

test('global search modal type dropdown updates type', function () {
    Livewire::test(GlobalSearchModal::class)
        ->set('search', 'market')
        ->call('$set', 'type', 'expenses')
        ->assertSet('type', 'expenses');
});

test('global search modal defaults to all type and default sort', function () {
    Livewire::test(GlobalSearchModal::class)
        ->assertSet('type', 'all')
        ->assertSet('sort', 'default');
});

test('global search modal toolbar highlights active type and sort with subtle active class', function () {
    $html = Livewire::test(GlobalSearchModal::class)
        ->set('search', 'market')
        ->set('type', 'expenses')
        ->set('sort', 'updated_desc')
        ->html();

    expect($html)
        ->toContain('fi-active')
        ->not->toContain('heroicon-o-check')
        ->not->toContain('heroicon-m-check');
});

test('global search modal resets state when modal closes', function () {
    Livewire::test(GlobalSearchModal::class)
        ->set('search', 'market')
        ->set('type', 'expenses')
        ->set('sort', 'date_desc')
        ->call('resetModalState')
        ->assertSet('search', '')
        ->assertSet('type', 'all')
        ->assertSet('sort', 'default');
});

test('type expenses hides pages sections and other resource groups', function () {
    Expense::factory()->create(['merchant_name' => 'UniqueExpenseOnlyShop']);
    Label::factory()->create(['name' => 'UniqueExpenseOnlyShop Label', 'slug' => 'unique-expense-only-shop-label']);

    $component = Livewire::test(GlobalSearchModal::class)
        ->set('type', 'expenses')
        ->set('search', 'UniqueExpenseOnlyShop');

    $results = $component->instance()->getResults();

    expect($results)->not->toBeNull()
        ->and($results->getCategories()->has('expenses'))->toBeTrue()
        ->and($results->getCategories()->has('labels'))->toBeFalse()
        ->and($results->getCategories()->has('Pages'))->toBeFalse()
        ->and($results->getCategories()->has('Sections'))->toBeFalse();
});

test('type pages only returns page destinations', function () {
    applyGlobalSearchCriteria(GlobalSearchType::Pages, 'relevance');

    $results = AdminDestinationSearch::search('Expenses', GlobalSearchResults::make());

    expect($results->getCategories()->has('Pages'))->toBeTrue()
        ->and($results->getCategories()->has('Sections'))->toBeFalse();
});

test('type sections only returns section destinations', function () {
    applyGlobalSearchCriteria(GlobalSearchType::Sections, 'relevance');

    $results = AdminDestinationSearch::search('Total Spent', GlobalSearchResults::make());

    expect($results->getCategories()->has('Sections'))->toBeTrue()
        ->and($results->getCategories()->has('Pages'))->toBeFalse();
});

test('expense global search filters by status before result limit', function () {
    Expense::factory()->count(25)->create([
        'merchant_name' => 'Filter Status Market',
        'status' => 'reviewed',
    ]);

    Expense::factory()->create([
        'merchant_name' => 'Filter Status Market',
        'status' => 'pending',
    ]);

    applyGlobalSearchCriteria(
        GlobalSearchType::Expenses,
        'updated_desc',
        [
            'expenses' => [
                'status' => 'pending',
                'source' => null,
                'family_member_id' => null,
                'payment_method_id' => null,
                'label_id' => null,
                'from' => null,
                'until' => null,
                'total_min' => null,
                'total_max' => null,
            ],
        ],
    );

    $results = ExpenseResource::getGlobalSearchResults('Filter Status Market');

    expect($results)->toHaveCount(1)
        ->and($results->first()->details['Status'])->toBe('pending');
});

test('expense global search filters by label before result limit', function () {
    $label = Label::factory()->create(['name' => 'Chicken Label']);

    $matchingExpense = Expense::factory()->create([
        'merchant_name' => 'Filter Label Market',
    ]);

    ExpenseItem::factory()
        ->for($matchingExpense)
        ->create([
            'description' => 'Chicken fillet',
            'label_id' => $label->getKey(),
        ]);

    Expense::factory()->count(25)->create([
        'merchant_name' => 'Filter Label Market',
    ]);

    applyGlobalSearchCriteria(
        GlobalSearchType::Expenses,
        'updated_desc',
        [
            'expenses' => [
                'status' => null,
                'source' => null,
                'family_member_id' => null,
                'payment_method_id' => null,
                'label_id' => $label->getKey(),
                'from' => null,
                'until' => null,
                'total_min' => null,
                'total_max' => null,
            ],
        ],
    );

    $results = ExpenseResource::getGlobalSearchResults('Filter Label Market');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Filter Label Market');
});

test('expense global search sorts by buy date newest first', function () {
    $this->travelTo(Carbon::parse('2026-08-31 12:00:00', 'Asia/Kuala_Lumpur'));

    Expense::factory()->create([
        'merchant_name' => 'Sort Date Market',
        'date_time' => Carbon::parse('2026-08-01 10:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    Expense::factory()->create([
        'merchant_name' => 'Sort Date Market',
        'date_time' => Carbon::parse('2026-08-20 10:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    applyGlobalSearchCriteria(GlobalSearchType::Expenses, 'date_desc');

    $results = ExpenseResource::getGlobalSearchResults('Sort Date Market');

    expect($results)->toHaveCount(2)
        ->and($results->first()->details['Buy Date'])->toBe('20 Aug 2026')
        ->and($results->last()->details['Buy Date'])->toBe('01 Aug 2026');
});

test('expense global search sorts by total high to low', function () {
    Expense::factory()->create([
        'merchant_name' => 'Sort Total Market',
        'total_amount' => '12.50',
    ]);

    Expense::factory()->create([
        'merchant_name' => 'Sort Total Market',
        'total_amount' => '88.00',
    ]);

    applyGlobalSearchCriteria(GlobalSearchType::Expenses, 'total_desc');

    $results = ExpenseResource::getGlobalSearchResults('Sort Total Market');

    expect($results)->toHaveCount(2)
        ->and($results->first()->details['Total'])->toBe('RM 88.00')
        ->and($results->last()->details['Total'])->toBe('RM 12.50');
});

test('family member type options omit primary only resources', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60118887777',
    ]);
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user);

    $options = GlobalSearchType::optionsForUser();

    expect($options)
        ->toHaveKey('expenses')
        ->toHaveKey('budgets')
        ->not->toHaveKey('labels')
        ->not->toHaveKey('payment_methods')
        ->not->toHaveKey('family_members')
        ->not->toHaveKey('backups');
});

test('primary user type options include all searchable resources', function () {
    $options = GlobalSearchType::optionsForUser();

    expect($options)
        ->toHaveKey('expenses')
        ->toHaveKey('budgets')
        ->toHaveKey('recurrings')
        ->toHaveKey('labels')
        ->toHaveKey('payment_methods')
        ->toHaveKey('family_members')
        ->toHaveKey('backups');
});

test('global search modal clear filters action resets active filters', function () {
    Livewire::test(GlobalSearchModal::class)
        ->set('type', 'expenses')
        ->set('filters.expenses.status', 'reviewed')
        ->call('clearFilters')
        ->assertSet('filters.expenses.status', null);
});

test('global search modal shows clear filters empty state when filters exclude all results', function () {
    Expense::factory()->create([
        'merchant_name' => 'Empty Filter Shop',
        'status' => 'reviewed',
    ]);

    $html = Livewire::test(GlobalSearchModal::class)
        ->set('search', 'Empty Filter Shop')
        ->set('type', 'expenses')
        ->set('filters.expenses.status', 'pending')
        ->html();

    expect($html)
        ->toContain('No Matches Found')
        ->toContain('Clear Filters');
});

test('destination search sorts pages by title ascending', function () {
    applyGlobalSearchCriteria(GlobalSearchType::Pages, 'title_asc');

    $results = AdminDestinationSearch::search('a', GlobalSearchResults::make());
    $pages = collect($results->getCategories()->get('Pages', []))->map(fn ($result): string => $result->title)->values()->all();

    expect($pages)->toBe(collect($pages)->sort()->values()->all());
});

test('all type title sort handles collection category results', function () {
    Expense::factory()->create(['merchant_name' => 'Zebra All Sort Market']);
    Expense::factory()->create(['merchant_name' => 'Alpha All Sort Market']);

    applyGlobalSearchCriteria(GlobalSearchType::All, 'title_asc');

    $results = app(TidoSearchEngine::class)->search('All Sort Market');
    $titles = collect($results->getCategories()->get('expenses', []))
        ->map(fn ($result): string => $result->title)
        ->values()
        ->all();

    expect($titles)->toBe(['Alpha All Sort Market', 'Zebra All Sort Market']);
});

test('budget global search filters by active status', function () {
    $label = Label::factory()->create(['name' => 'Budget Filter Label']);

    BudgetResource::getModel()::factory()
        ->for($label)
        ->create([
            'title' => 'Budget Filter Label Active',
            'is_active' => true,
        ]);

    BudgetResource::getModel()::factory()
        ->for($label)
        ->create([
            'title' => 'Budget Filter Label Inactive',
            'is_active' => false,
        ]);

    applyGlobalSearchCriteria(
        GlobalSearchType::Budgets,
        'updated_desc',
        [
            'budgets' => [
                'period' => null,
                'family_member_id' => null,
                'is_shared' => null,
                'is_active' => '0',
            ],
        ],
    );

    $results = BudgetResource::getGlobalSearchResults('Budget Filter Label');

    expect($results)->toHaveCount(1)
        ->and($results->first()->details['Active'])->toBe('No');
});

test('backup global search filters by updated date range', function () {
    $this->travelTo(Carbon::parse('2026-08-31 12:00:00', 'Asia/Kuala_Lumpur'));

    BackupResource::getModel()::factory()->create([
        'filename' => 'backup-filter-old.zip',
        'updated_at' => Carbon::parse('2026-07-01 12:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    BackupResource::getModel()::factory()->create([
        'filename' => 'backup-filter-new.zip',
        'updated_at' => Carbon::parse('2026-08-20 12:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    applyGlobalSearchCriteria(
        GlobalSearchType::Backups,
        'updated_desc',
        [
            'backups' => [
                'from' => '2026-08-01',
                'until' => null,
            ],
        ],
    );

    $results = BackupResource::getGlobalSearchResults('backup-filter');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('backup-filter-new.zip');
});

test('payment method global search filters by system lock', function () {
    PaymentMethodResource::getModel()::factory()->create([
        'name' => 'Filter Payment System',
        'slug' => 'filter-payment-system',
        'is_system' => true,
    ]);

    PaymentMethodResource::getModel()::factory()->create([
        'name' => 'Filter Payment Custom',
        'slug' => 'filter-payment-custom',
        'is_system' => false,
    ]);

    applyGlobalSearchCriteria(
        GlobalSearchType::PaymentMethods,
        'updated_desc',
        [
            'payment_methods' => [
                'is_system' => '0',
            ],
        ],
    );

    $results = PaymentMethodResource::getGlobalSearchResults('Filter Payment');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Filter Payment Custom');
});

test('family member global search filters by panel login', function () {
    FamilyMemberResource::getModel()::factory()->create([
        'name' => 'Filter Member Login Off',
        'phone' => '60121110001',
        'login_enabled' => false,
    ]);

    FamilyMemberResource::getModel()::factory()->loginEnabled()->create([
        'name' => 'Filter Member Login On',
        'phone' => '60121110002',
    ]);

    applyGlobalSearchCriteria(
        GlobalSearchType::FamilyMembers,
        'updated_desc',
        [
            'family_members' => [
                'allowlist_enabled' => null,
                'login_enabled' => '1',
            ],
        ],
    );

    $results = FamilyMemberResource::getGlobalSearchResults('Filter Member Login');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Filter Member Login On');
});
