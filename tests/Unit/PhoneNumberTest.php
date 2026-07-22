<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('normalizes local Malaysian numbers to 60 prefix', function () {
    expect(PhoneNumber::normalize('0123456789'))->toBe('60123456789');
});

test('normalizes plus-prefixed E.164 numbers', function () {
    expect(PhoneNumber::normalize('+60123456789'))->toBe('60123456789');
});

test('keeps already-normalized digits', function () {
    expect(PhoneNumber::normalize('60123456789'))->toBe('60123456789');
});

test('rejects empty and invalid values', function (mixed $value) {
    expect(PhoneNumber::normalize(is_string($value) ? $value : null))->toBeNull()
        ->and(PhoneNumber::isValid(is_string($value) ? $value : null))->toBeFalse();
})->with([
    null,
    '',
    '   ',
    '12345',
    'abc',
    '+1-555-0100',
]);

test('parseList splits and normalizes multiple numbers', function () {
    expect(PhoneNumber::parseList('60111111111, 0122222222;+60133333333'))
        ->toBe(['60111111111', '60122222222', '60133333333']);
});

test('parseList ignores invalid entries', function () {
    expect(PhoneNumber::parseList('60111111111, abc, 123'))
        ->toBe(['60111111111']);
});

test('allowedWhatsAppSenders includes profile and allowlisted family members', function () {
    User::factory()->create(['phone' => '60123456789']);
    FamilyMember::factory()->create([
        'name' => 'Spouse',
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);
    FamilyMember::factory()->create([
        'name' => 'Sibling',
        'phone' => '60122222222',
        'allowlist_enabled' => true,
    ]);
    FamilyMember::factory()->notAllowlisted()->create([
        'phone' => '60133333333',
    ]);

    expect(PhoneNumber::allowedWhatsAppSenders())
        ->toBe(['60123456789', '60111111111', '60122222222']);
});

test('isAllowedWhatsAppSender matches profile and allowlisted family only', function () {
    User::factory()->create(['phone' => '60123456789']);
    FamilyMember::factory()->create([
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);
    FamilyMember::factory()->notAllowlisted()->create([
        'phone' => '60133333333',
    ]);

    expect(PhoneNumber::isAllowedWhatsAppSender('60123456789'))->toBeTrue()
        ->and(PhoneNumber::isAllowedWhatsAppSender('60111111111'))->toBeTrue()
        ->and(PhoneNumber::isAllowedWhatsAppSender('60133333333'))->toBeFalse()
        ->and(PhoneNumber::isAllowedWhatsAppSender('60199999999'))->toBeFalse();
});

test('primaryWhatsAppNumber returns first user phone by id', function () {
    User::factory()->create(['phone' => '60111111111']);
    User::factory()->create(['phone' => '60122222222']);

    expect(PhoneNumber::primaryWhatsAppNumber())->toBe('60111111111');
});

test('allowedWhatsAppSenderEntries labels profile and family members', function () {
    User::factory()->create([
        'name' => 'Admin User',
        'phone' => '60123456789',
    ]);
    FamilyMember::factory()->create([
        'name' => 'Spouse',
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);

    expect(PhoneNumber::allowedWhatsAppSenderEntries())->toBe([
        [
            'label' => 'Profile · Admin User',
            'phone' => '60123456789',
        ],
        [
            'label' => 'Spouse',
            'phone' => '60111111111',
        ],
    ]);
});
