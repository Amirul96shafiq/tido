<?php

declare(strict_types=1);

use App\Models\PaymentMethod;
use App\Services\PaymentMethodMatcher;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PaymentMethodSeeder::class);
});

test('matcher resolves common payment method aliases', function (string $input, string $expectedSlug) {
    $method = app(PaymentMethodMatcher::class)->match($input);

    expect($method)->not->toBeNull()
        ->and($method->slug)->toBe($expectedSlug);
})->with([
    ['mastercard', 'mastercard'],
    ['card', 'mastercard'],
    ['Visa', 'visa'],
    ['MYKASIH', 'mykasih'],
    ['cash', 'cash'],
    ['Cash', 'cash'],
    ['pay_with_qr', 'pay_with_qr'],
    ['Pay with QR', 'pay_with_qr'],
    ['QR Pay', 'pay_with_qr'],
    ['duitnow qr', 'pay_with_qr'],
    ['touchngo', 'touchngo'],
    ["Touch 'n Go", 'touchngo'],
    ['TNG', 'touchngo'],
    ['tngo', 'touchngo'],
    ['debit', 'other'],
    ['credit', 'other'],
    ['debit_card', 'other'],
    ['credit_card', 'other'],
    ['other', 'other'],
]);

test('matcher returns null for blank or unknown values', function () {
    $matcher = app(PaymentMethodMatcher::class);

    expect($matcher->match(null))->toBeNull()
        ->and($matcher->match(''))->toBeNull()
        ->and($matcher->match('bitcoin'))->toBeNull()
        ->and($matcher->matchId('bitcoin'))->toBeNull();
});

test('matcher resolves custom payment methods by name and aliases', function () {
    $grabPay = PaymentMethod::factory()->create([
        'name' => 'GrabPay',
        'slug' => 'grabpay',
        'aliases' => ['grab', 'grab_pay'],
    ]);

    $matcher = app(PaymentMethodMatcher::class);

    expect($matcher->matchId('GrabPay'))->toBe($grabPay->id)
        ->and($matcher->matchId('grab'))->toBe($grabPay->id)
        ->and($matcher->matchId('grab_pay'))->toBe($grabPay->id);
});

test('whatsapp token map includes seeded aliases', function () {
    $map = app(PaymentMethodMatcher::class)->whatsappTokenMap();

    expect($map)->toHaveKey('qr')
        ->and($map['qr']->slug)->toBe('pay_with_qr')
        ->and($map)->toHaveKey('tngo')
        ->and($map['tngo']->slug)->toBe('touchngo')
        ->and($map)->toHaveKey('card')
        ->and($map['card']->slug)->toBe('mastercard');
});

test('default cash resolves seeded cash method', function () {
    $cash = app(PaymentMethodMatcher::class)->defaultCash();

    expect($cash)->not->toBeNull()
        ->and($cash->slug)->toBe('cash');
});

test('seeded system payment methods exist with expected icons', function () {
    expect(PaymentMethod::findBySlug('cash')?->icon)->toBe('heroicon-o-banknotes')
        ->and(PaymentMethod::findBySlug('pay_with_qr')?->icon)->toBe('heroicon-o-qr-code')
        ->and(PaymentMethod::findBySlug('mykasih')?->icon)->toBe('heroicon-o-identification')
        ->and(PaymentMethod::query()->system()->count())->toBe(7);
});
