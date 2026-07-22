<?php

declare(strict_types=1);

use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Models\Backup;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use CharrafiMed\GlobalSearchModal\Livewire\GlobalSearchModal;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
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
        FamilyMemberResource::class,
        InvoiceResource::class,
        LabelResource::class,
        PaymentMethodResource::class,
    ]);
});

test('global search opt-in requires explicit resource declaration', function () {
    $declaringClass = (new ReflectionProperty(InvoiceResource::class, 'isGloballySearchable'))
        ->getDeclaringClass()
        ->getName();

    expect($declaringClass)->toBe(InvoiceResource::class);
});

test('invoice global search finds merchant name', function () {
    $invoice = Invoice::factory()->create([
        'merchant_name' => 'UniqueMerchantXYZ',
    ]);

    $results = InvoiceResource::getGlobalSearchResults('UniqueMerchantXYZ');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('UniqueMerchantXYZ');
});

test('invoice global search finds line item description', function () {
    $invoice = Invoice::factory()->create([
        'merchant_name' => 'Generic Store',
    ]);

    InvoiceItem::factory()
        ->for($invoice)
        ->create([
            'description' => 'Organic Almond Milk Special',
        ]);

    $results = InvoiceResource::getGlobalSearchResults('Almond Milk');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Generic Store')
        ->and($results->first()->details)->toHaveKey('Items')
        ->and($results->first()->details['Items'])->toContain('Organic Almond Milk Special');
});

test('invoice global search omits items detail when only merchant matches', function () {
    Invoice::factory()->create([
        'merchant_name' => 'Cake Bakery Only',
    ]);

    $results = InvoiceResource::getGlobalSearchResults('Cake Bakery Only');

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
