<?php

declare(strict_types=1);

use App\Models\Label;
use App\Models\PaymentMethod;
use App\Prompts\ReceiptExtractionPrompt;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('receipt extraction prompt includes all finance labels with descriptions', function () {
    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

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

test('receipt extraction prompt includes available payment methods with aliases', function () {
    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $prompt = ReceiptExtractionPrompt::build();

    expect($prompt)
        ->toContain('Available payment methods')
        ->toContain('- Cash')
        ->toContain('- Pay with QR — aliases: qr, qr_pay, qr_payment, duitnow_qr, duitnow')
        ->toContain('- Visa')
        ->toContain('- Mastercard — aliases: master, master_card, card, mc');
});

test('receipt extraction prompt includes payment method notes hints', function () {
    $this->seed(LabelSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    PaymentMethod::factory()->create([
        'name' => 'GrabPay',
        'slug' => 'grabpay',
        'aliases' => ['grab'],
        'notes' => '<p>Grab e-wallet at merchants</p>',
    ]);

    $prompt = ReceiptExtractionPrompt::build();

    expect($prompt)
        ->toContain('GrabPay — aliases: grab; Grab e-wallet at merchants')
        ->not->toContain('<p>');
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

test('receipt extraction prompt strips html from label descriptions', function () {
    $this->seed(LabelSeeder::class);

    Label::factory()->create([
        'name' => 'Pet Supplies',
        'slug' => 'pet-supplies',
        'description' => '<p>Pet food, <strong>grooming</strong>, vet supplies</p>',
    ]);

    $prompt = ReceiptExtractionPrompt::build();

    expect($prompt)
        ->toContain('Pet Supplies — Pet food, grooming, vet supplies')
        ->not->toContain('<p>')
        ->not->toContain('<strong>');
});

test('receipt extraction prompt get delegates to build', function () {
    $this->seed(LabelSeeder::class);

    expect(ReceiptExtractionPrompt::get())->toBe(ReceiptExtractionPrompt::build());
});
