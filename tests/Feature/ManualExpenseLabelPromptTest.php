<?php

declare(strict_types=1);

use App\Models\Label;
use App\Prompts\ManualExpenseLabelPrompt;
use Database\Seeders\LabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('manual expense label prompt includes furniture and groceries disambiguation rules', function () {
    $this->seed(LabelSeeder::class);

    Label::factory()->create([
        'name' => 'Furniture & Home Appliances',
        'slug' => 'furniture-home-appliances',
        'description' => 'Durable furniture, mattresses, bedding sets, and home appliances (fridge, washer, aircon, vacuum, kettle, rice cooker, water filters). Home improvement durables from DIY/hardware stores.',
    ]);

    $prompt = ManualExpenseLabelPrompt::build(['Baby wipes 80s', 'Mattress king size']);

    expect($prompt)
        ->toContain('Line items to classify:')
        ->toContain('1. Baby wipes 80s')
        ->toContain('2. Mattress king size')
        ->toContain('Groceries & Household → consumable / replenishable pantry')
        ->toContain('Furniture & Home Appliances → durable furniture, mattresses, bed frames')
        ->toContain('Do not classify durable furniture or appliances as Groceries & Household')
        ->toContain('Electronics & Gadgets → phones, computers, tablets, accessories, cables')
        ->toContain('Furniture & Home Appliances — Durable furniture, mattresses, bedding sets');
});
