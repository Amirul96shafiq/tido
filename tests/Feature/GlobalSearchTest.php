<?php

declare(strict_types=1);

use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Labels\LabelResource;
use App\Models\Backup;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Label;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('only configured resources are globally searchable', function () {
    $searchable = collect(Filament::getResources())
        ->filter(fn (string $resource): bool => $resource::canGloballySearch())
        ->sort()
        ->values()
        ->all();

    expect($searchable)->toBe([
        BackupResource::class,
        BudgetResource::class,
        InvoiceResource::class,
        LabelResource::class,
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
