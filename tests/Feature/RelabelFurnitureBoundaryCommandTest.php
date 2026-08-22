<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Label;
use App\Services\LabelBoundaryRelabelService;
use Database\Seeders\LabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('relabel furniture boundary command syncs label descriptions and relabels consumables', function () {
    $this->seed(LabelSeeder::class);

    Label::factory()->create([
        'name' => 'Furniture & Home Appliances',
        'slug' => 'furniture-home-appliances',
        'description' => '',
    ]);

    $groceries = Label::query()->where('slug', 'groceries-household')->firstOrFail();
    $furniture = Label::query()->where('slug', 'furniture-home-appliances')->firstOrFail();

    $expense = Expense::factory()->create();

    foreach (LabelBoundaryRelabelService::FURNITURE_TO_GROCERIES_ITEM_IDS as $itemId) {
        ExpenseItem::factory()->create([
            'id' => $itemId,
            'expense_id' => $expense->id,
            'label_id' => $furniture->id,
            'description' => "Item {$itemId}",
        ]);
    }

    ExpenseItem::factory()->create([
        'id' => 9999,
        'expense_id' => $expense->id,
        'label_id' => $furniture->id,
        'description' => 'Mattress king size',
    ]);

    Artisan::call('labels:relabel-furniture-boundary');

    expect(Artisan::output())
        ->toContain('Furniture → Groceries: 9')
        ->toContain('Groceries → Furniture: 0');

    foreach (LabelBoundaryRelabelService::FURNITURE_TO_GROCERIES_ITEM_IDS as $itemId) {
        expect(ExpenseItem::query()->findOrFail($itemId)->label_id)->toBe($groceries->id);
    }

    expect(ExpenseItem::query()->findOrFail(9999)->label_id)->toBe($furniture->id);

    expect($groceries->fresh()->description)
        ->toBe(LabelBoundaryRelabelService::LABEL_DESCRIPTIONS['groceries-household']);

    expect($furniture->fresh()->description)
        ->toBe(LabelBoundaryRelabelService::LABEL_DESCRIPTIONS['furniture-home-appliances']);
});

test('relabel furniture boundary command is idempotent', function () {
    $this->seed(LabelSeeder::class);

    Label::factory()->create([
        'name' => 'Furniture & Home Appliances',
        'slug' => 'furniture-home-appliances',
        'description' => LabelBoundaryRelabelService::LABEL_DESCRIPTIONS['furniture-home-appliances'],
    ]);

    $groceries = Label::query()->where('slug', 'groceries-household')->firstOrFail();
    $furniture = Label::query()->where('slug', 'furniture-home-appliances')->firstOrFail();
    $expense = Expense::factory()->create();

    ExpenseItem::factory()->create([
        'id' => 544,
        'expense_id' => $expense->id,
        'label_id' => $furniture->id,
    ]);

    $service = app(LabelBoundaryRelabelService::class);

    $firstRun = $service->run();
    $secondRun = $service->run();

    expect($firstRun['furniture_to_groceries'])->toBe(1)
        ->and($secondRun['furniture_to_groceries'])->toBe(0)
        ->and(ExpenseItem::query()->findOrFail(544)->label_id)->toBe($groceries->id);
});
