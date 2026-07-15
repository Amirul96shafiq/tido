<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

test('payment method options include pay with qr and touch n go', function () {
    $options = PaymentMethod::options();

    expect($options)
        ->toHaveKey(PaymentMethod::PayWithQr->value, 'Pay with QR')
        ->toHaveKey(PaymentMethod::TouchNGo->value, "Touch 'n Go")
        ->toHaveKey(PaymentMethod::Mastercard->value, 'Mastercard')
        ->toHaveKey(PaymentMethod::Cash->value, 'Cash');
});

test('each payment method has a label icon and color', function (PaymentMethod $method) {
    expect($method->getLabel())->toBeString()->not->toBeEmpty()
        ->and($method->getIcon())->not->toBeNull()
        ->and($method->getColor())->toBeString()->not->toBeEmpty();
})->with(PaymentMethod::cases());

test('payment method icons map to expected heroicons', function () {
    expect(PaymentMethod::Mykasih->getIcon())->toBe(Heroicon::Identification)
        ->and(PaymentMethod::Cash->getIcon())->toBe(Heroicon::Banknotes)
        ->and(PaymentMethod::PayWithQr->getIcon())->toBe(Heroicon::QrCode)
        ->and(PaymentMethod::Other->getIcon())->toBe(Heroicon::EllipsisHorizontal);
});

test('visa and mastercard use distinct custom icons', function () {
    $visaIcon = PaymentMethod::Visa->getIcon();
    $mastercardIcon = PaymentMethod::Mastercard->getIcon();

    expect($visaIcon)->toBeInstanceOf(Htmlable::class)
        ->and($mastercardIcon)->toBeInstanceOf(Htmlable::class)
        ->and($visaIcon->toHtml())->not->toBe($mastercardIcon->toHtml())
        ->and($visaIcon->toHtml())->toContain('<svg')
        ->and($mastercardIcon->toHtml())->toContain('<circle')
        ->and(PaymentMethod::Visa->getColor())->toBe('info')
        ->and(PaymentMethod::Mastercard->getColor())->toBe('warning');
});

test('touch n go uses the ewallet svg mark', function () {
    $icon = PaymentMethod::TouchNGo->getIcon();

    expect($icon)->toBeInstanceOf(Htmlable::class)
        ->and($icon->toHtml())->toContain('viewBox="0 0 48 48"')
        ->and($icon->toHtml())->toContain('stroke="currentColor"')
        ->and($icon->toHtml())->toContain('M42.451');
});

test('try from ai resolves common payment method aliases', function (string $input, PaymentMethod $expected) {
    expect(PaymentMethod::tryFromAi($input))->toBe($expected);
})->with([
    ['mastercard', PaymentMethod::Mastercard],
    ['Visa', PaymentMethod::Visa],
    ['MYKASIH', PaymentMethod::Mykasih],
    ['cash', PaymentMethod::Cash],
    ['pay_with_qr', PaymentMethod::PayWithQr],
    ['QR Pay', PaymentMethod::PayWithQr],
    ['duitnow qr', PaymentMethod::PayWithQr],
    ['touchngo', PaymentMethod::TouchNGo],
    ["Touch 'n Go", PaymentMethod::TouchNGo],
    ['TNG', PaymentMethod::TouchNGo],
    ['debit', PaymentMethod::Other],
    ['credit', PaymentMethod::Other],
    ['debit_card', PaymentMethod::Other],
    ['credit_card', PaymentMethod::Other],
    ['other', PaymentMethod::Other],
]);

test('try from ai returns null for blank or unknown values', function () {
    expect(PaymentMethod::tryFromAi(null))->toBeNull()
        ->and(PaymentMethod::tryFromAi(''))->toBeNull()
        ->and(PaymentMethod::tryFromAi('bitcoin'))->toBeNull();
});
