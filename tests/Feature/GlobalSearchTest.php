<?php

declare(strict_types=1);

use App\Filament\GlobalSearch\AdminDestinationSearch;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Widgets\CurrentCurrency;
use App\Models\Backup;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use CharrafiMed\GlobalSearchModal\GlobalSearchResults;
use CharrafiMed\GlobalSearchModal\Livewire\GlobalSearchModal;
use CharrafiMed\GlobalSearchModal\SearchEngine;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('destination search finds dashboard total spent section', function () {
    $results = AdminDestinationSearch::search('Total Spent', GlobalSearchResults::make());
    $sections = collect($results->getCategories()->get('Sections', []));

    $match = $sections->first(fn ($result): bool => $result->title === 'Total Spent');

    expect($match)->not->toBeNull()
        ->and($match->url)->toEndWith('#total-spent')
        ->and($match->details)->toBe(['Page' => 'Dashboard']);
});

test('destination search finds dashboard spending forecast section', function () {
    $results = AdminDestinationSearch::search('Spending Forecast', GlobalSearchResults::make());
    $sections = collect($results->getCategories()->get('Sections', []));

    $match = $sections->first(fn ($result): bool => $result->title === 'Spending Forecast');

    expect($match)->not->toBeNull()
        ->and($match->url)->toEndWith('#spending-forecast')
        ->and($match->details)->toBe(['Page' => 'Dashboard']);
});

test('destination search finds dashboard usd to myr section', function () {
    $results = AdminDestinationSearch::search('USD to MYR', GlobalSearchResults::make());
    $sections = collect($results->getCategories()->get('Sections', []));

    $match = $sections->first(fn ($result): bool => $result->title === 'USD to MYR');

    expect($match)->not->toBeNull()
        ->and($match->url)->toEndWith('#'.CurrentCurrency::SECTION_CURRENCY_RATE)
        ->and($match->details)->toBe(['Page' => 'Dashboard']);
});

test('destination search finds dashboard daily average section', function () {
    $results = AdminDestinationSearch::search('Daily Average', GlobalSearchResults::make());
    $sections = collect($results->getCategories()->get('Sections', []));

    $match = $sections->first(fn ($result): bool => $result->title === 'Daily Average');

    expect($match)->not->toBeNull()
        ->and($match->url)->toEndWith('#spending-forecast')
        ->and($match->details)->toBe(['Page' => 'Dashboard']);
});

test('destination search finds profile account and security section', function () {
    $results = AdminDestinationSearch::search('Account Security', GlobalSearchResults::make());
    $sections = collect($results->getCategories()->get('Sections', []));

    $match = $sections->first(fn ($result): bool => $result->title === 'Account & Security');

    expect($match)->not->toBeNull()
        ->and($match->url)->toEndWith('#account-security')
        ->and($match->details)->toBe(['Page' => 'Profile']);
});

test('destination search hides account and security for family members', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60118887777',
    ]);
    $user = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($user);

    $results = AdminDestinationSearch::search('Account Security', GlobalSearchResults::make());
    $sections = collect($results->getCategories()->get('Sections', []));

    expect($sections->first(fn ($result): bool => $result->title === 'Account & Security'))->toBeNull();
});

test('destination search finds profile active sessions section', function () {
    $results = AdminDestinationSearch::search('Active Sessions', GlobalSearchResults::make());
    $sections = collect($results->getCategories()->get('Sections', []));

    $match = $sections->first(fn ($result): bool => $result->title === 'Active Sessions');

    expect($match)->not->toBeNull()
        ->and($match->url)->toEndWith('#active-sessions')
        ->and($match->details)->toBe(['Page' => 'Profile']);
});

test('destination search finds evolutionapi page', function () {
    $results = AdminDestinationSearch::search('Evolution API', GlobalSearchResults::make());
    $pages = collect($results->getCategories()->get('Pages', []));

    $match = $pages->first(fn ($result): bool => $result->title === 'Evolution API');

    expect($match)->not->toBeNull()
        ->and($match->url)->toBe(EvolutionApiPage::getUrl());
});

test('destination search finds invoices list page', function () {
    $results = AdminDestinationSearch::search('Expenses', GlobalSearchResults::make());
    $pages = collect($results->getCategories()->get('Pages', []));

    $match = $pages->first(fn ($result): bool => $result->title === 'Expenses');

    expect($match)->not->toBeNull()
        ->and($match->url)->toBe(ExpenseResource::getUrl('index'));
});

test('destination search finds backups list page', function () {
    $results = AdminDestinationSearch::search('Backups', GlobalSearchResults::make());
    $pages = collect($results->getCategories()->get('Pages', []));

    $match = $pages->first(fn ($result): bool => $result->title === 'Backups');

    expect($match)->not->toBeNull()
        ->and($match->url)->toBe(BackupResource::getUrl('index'));
});

test('search engine merges destination results with resource records', function () {
    Expense::factory()->create([
        'merchant_name' => 'Expenses Corner Shop',
    ]);

    $results = app(SearchEngine::class)->search('Expenses');

    expect($results)->not->toBeNull()
        ->and($results->getCategories()->has('Pages'))->toBeTrue()
        ->and($results->getCategories()->has('expenses'))->toBeTrue();
});

test('profile edit page exposes searchable section anchors', function () {
    $html = Livewire::test(EditProfile::class)->html();

    expect($html)
        ->toContain('id="account-security"')
        ->toContain('id="active-sessions"')
        ->toContain('id="personalize"')
        ->toContain('id="danger-zone"')
        ->toContain('id="profile-photo"')
        ->toContain('id="personal-details"');
});

test('admin panel includes spa-safe hash scroll helper', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSee('__tidoHashScrollInstalled', false);
});

test('section anchors include scroll margin offset for sticky topbar', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-main-ctn [id]')
        ->toContain('scroll-margin-top: 5rem');
});

test('admin panel requires global search resource opt-in', function () {
    expect(filament()->getCurrentOrDefaultPanel()->isGlobalSearchResourceOptIn())->toBeTrue();
});

test('admin panel opens global search with alt+k', function () {
    expect(filament()->getCurrentOrDefaultPanel()->getGlobalSearchKeyBindings())->toBe(['alt+k']);
});

test('admin panel includes spa-safe alt+k global search shortcut', function () {

    $user = User::factory()->withWhatsAppPhone('60123456789')->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertSee('__tidoGlobalSearchShortcutInstalled', false);
});

test('only configured resources are globally searchable', function () {
    $searchable = collect(Filament::getResources())
        ->filter(fn (string $resource): bool => $resource::canGloballySearch())
        ->sort()
        ->values()
        ->all();

    expect($searchable)->toBe([
        BackupResource::class,
        BudgetResource::class,
        ExpenseResource::class,
        FamilyMemberResource::class,
        LabelResource::class,
        PaymentMethodResource::class,
    ]);
});

test('global search opt-in requires explicit resource declaration', function () {
    $declaringClass = (new ReflectionProperty(ExpenseResource::class, 'isGloballySearchable'))
        ->getDeclaringClass()
        ->getName();

    expect($declaringClass)->toBe(ExpenseResource::class);
});

test('invoice global search finds merchant name', function () {
    $invoice = Expense::factory()->create([
        'merchant_name' => 'UniqueMerchantXYZ',
    ]);

    $results = ExpenseResource::getGlobalSearchResults('UniqueMerchantXYZ');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('UniqueMerchantXYZ');
});

test('invoice global search finds status', function () {
    $invoice = Expense::factory()->create([
        'merchant_name' => 'Status Store',
        'invoice_number' => 'INV-STATUS-XYZ',
        'notes' => 'Ordinary invoice note.',
        'original_filename' => 'receipt.jpg',
        'status' => 'reviewed',
    ]);

    $results = ExpenseResource::getGlobalSearchResults('reviewed');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Status Store');
});

test('invoice global search finds line item description', function () {
    $invoice = Expense::factory()->create([
        'merchant_name' => 'Generic Store',
    ]);

    ExpenseItem::factory()
        ->for($invoice)
        ->create([
            'description' => 'Organic Almond Milk Special',
        ]);

    $results = ExpenseResource::getGlobalSearchResults('Almond Milk');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Generic Store')
        ->and($results->first()->details)->toHaveKey('Items')
        ->and($results->first()->details['Items'])->toContain('Organic Almond Milk Special');
});

test('global search highlights matching detail values with the primary color', function () {
    $invoice = Expense::factory()->create([
        'merchant_name' => 'Generic Store',
    ]);

    ExpenseItem::factory()
        ->for($invoice)
        ->create([
            'description' => 'Organic Almond Milk Special',
        ]);

    $html = Livewire::test(GlobalSearchModal::class)
        ->set('search', 'Almond Milk')
        ->html();

    expect($html)
        ->toContain('<span class="text-primary-500 font-semibold hover:underline">Almond Milk</span>')
        ->toContain('Organic <span class="text-primary-500 font-semibold hover:underline">Almond Milk</span> Special');
});

test('invoice global search omits items detail when only merchant matches', function () {
    Expense::factory()->create([
        'merchant_name' => 'Cake Bakery Only',
    ]);

    $results = ExpenseResource::getGlobalSearchResults('Cake Bakery Only');

    expect($results)->toHaveCount(1)
        ->and($results->first()->details)->not->toHaveKey('Items');
});

test('label global search finds slug', function () {
    Label::factory()->create([
        'name' => 'Test Label',
        'slug' => 'unique-slug-xyz',
    ]);

    $results = LabelResource::getGlobalSearchResults('unique-slug-xyz');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Test Label');
});

test('payment method global search finds slug', function () {
    PaymentMethod::factory()->create([
        'name' => 'GrabPay Unique',
        'slug' => 'grabpay-unique-xyz',
    ]);

    $results = PaymentMethodResource::getGlobalSearchResults('grabpay-unique-xyz');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('GrabPay Unique');
});

test('budget global search finds label name', function () {
    $label = Label::factory()->create([
        'name' => 'Groceries Unique',
    ]);

    Budget::factory()
        ->for($label)
        ->create();

    $results = BudgetResource::getGlobalSearchResults('Groceries Unique');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toContain('Groceries Unique');
});

test('backup global search finds filename and links to index', function () {
    Backup::factory()->create([
        'filename' => 'tido-unique-backup-xyz.zip',
    ]);

    $results = BackupResource::getGlobalSearchResults('unique-backup-xyz');

    expect($results)->toHaveCount(1)
        ->and($results->first()->url)->toBe(BackupResource::getUrl('index'));
});

test('global search modal section headers use panel primary color', function () {
    $this->actingAs(User::factory()->create());

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $html = Livewire::test(GlobalSearchModal::class)->html();

    expect($html)
        ->toContain('text-primary-600')
        ->toContain('text-primary-500')
        ->not->toContain('text-violet-600')
        ->not->toContain('text-violet-500');
});

test('global search keybinding suffix matches collapsed sidebar group title style', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $sidebarGroup = (string) file_get_contents(
        resource_path('views/vendor/filament-panels/components/sidebar/group.blade.php'),
    );

    expect($sidebarGroup)
        ->toContain('text-[9px] font-bold tracking-wider text-gray-400 uppercase dark:text-slate-500')
        ->and($css)
        ->toContain('.fi-global-search-field .fi-input-wrp-label')
        ->toContain('text-[9px]')
        ->toContain('font-bold')
        ->toContain('tracking-wider')
        ->toContain('uppercase')
        ->toContain('text-gray-400')
        ->toContain('dark:text-slate-500');
});

test('topbar global search collapses to icon button on small screens', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('@media (max-width: 1023px)')
        ->toContain('.fi-topbar .fi-global-search')
        ->toContain('.fi-topbar .fi-global-search-field')
        ->toContain('.fi-topbar .fi-global-search-field .fi-input-wrp')
        ->toContain('.fi-topbar .fi-global-search-field .fi-input-wrp-prefix')
        ->toContain('.fi-topbar .fi-global-search-field .fi-input-wrp-suffix')
        ->toContain('.fi-topbar .fi-global-search-field .fi-input')
        ->toContain('collapse topbar global search to a size-10 icon button')
        ->toContain('flex-none')
        ->toContain('opacity-0')
        ->toContain('[id="global-search-modal::plugin"] .fi-modal-footer')
        ->toContain('hide keyboard shortcut footer');
});
