<?php

declare(strict_types=1);

use App\Models\Label;
use App\Services\LabelMatcher;
use Database\Seeders\LabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('label matcher resolves by slug derived name', function () {
    $this->seed(LabelSeeder::class);

    $matcher = new LabelMatcher;
    $foodLabel = Label::query()->where('slug', 'food-dining')->firstOrFail();

    expect($matcher->matchId('Food & Dining'))->toBe($foodLabel->id);
});

test('label matcher resolves case insensitive exact name', function () {
    $this->seed(LabelSeeder::class);

    $matcher = new LabelMatcher;
    $foodLabel = Label::query()->where('slug', 'food-dining')->firstOrFail();

    expect($matcher->matchId('food & dining'))->toBe($foodLabel->id);
});

test('label matcher returns null for unknown label', function () {
    $this->seed(LabelSeeder::class);

    $matcher = new LabelMatcher;

    expect($matcher->matchId('Unknown Category'))->toBeNull()
        ->and($matcher->matchId(null))->toBeNull()
        ->and($matcher->matchId(''))->toBeNull();
});

test('label matcher resolves user created labels', function () {
    $this->seed(LabelSeeder::class);

    $custom = Label::factory()->create([
        'name' => 'Pet Supplies',
        'slug' => 'pet-supplies',
    ]);

    $matcher = new LabelMatcher;

    expect($matcher->matchId('Pet Supplies'))->toBe($custom->id);
});
