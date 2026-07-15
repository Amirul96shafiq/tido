<?php

declare(strict_types=1);

use App\Models\Label;
use App\Prompts\ReceiptExtractionPrompt;
use Database\Seeders\LabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('receipt extraction prompt includes all finance labels with descriptions', function () {
    $this->seed(LabelSeeder::class);

    $prompt = ReceiptExtractionPrompt::build();

    expect($prompt)
        ->toContain('Available labels')
        ->toContain('"label": "String - exact label name from the list above"')
        ->toContain('Every item in items[] MUST include a label')
        ->toContain('Food & Dining — Ready-to-eat meals, restaurant items, convenience-store snacks and drinks')
        ->toContain('Groceries & Household — Supermarket pantry, fresh produce, cleaning supplies, baby wipes')
        ->toContain('Gardenia Original Classic Bread')
        ->toContain('Packaged bread loaves')
        ->not->toContain('suggested_category');
});

test('receipt extraction prompt includes user created finance labels', function () {
    $this->seed(LabelSeeder::class);

    Label::factory()->create([
        'name' => 'Pet Supplies',
        'slug' => 'pet-supplies',
        'description' => 'Pet food, grooming, vet supplies',
    ]);

    $prompt = ReceiptExtractionPrompt::build();

    expect($prompt)->toContain('Pet Supplies — Pet food, grooming, vet supplies');
});

test('receipt extraction prompt get delegates to build', function () {
    $this->seed(LabelSeeder::class);

    expect(ReceiptExtractionPrompt::get())->toBe(ReceiptExtractionPrompt::build());
});
