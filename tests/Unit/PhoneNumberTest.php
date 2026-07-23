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

test('builds wa.me chat url with default help text', function () {
    expect(PhoneNumber::whatsAppMeUrl('0123456789'))
        ->toBe('https://wa.me/60123456789?text=help')
        ->and(PhoneNumber::whatsAppMeUrl('+60123456789', 'ping'))
        ->toBe('https://wa.me/60123456789?text=ping')
        ->and(PhoneNumber::whatsAppMeUrl(null))
        ->toBeNull()
        ->and(PhoneNumber::whatsAppMeUrl('invalid'))
        ->toBeNull();
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
    '6011163307051', // 13 digits — extra trailing digit typo
]);

test('accepts eleven and twelve digit Malaysian numbers', function (string $value, string $expected) {
    expect(PhoneNumber::normalize($value))->toBe($expected);
})->with([
    ['60123456789', '60123456789'],
    ['601123456789', '601123456789'],
    ['0123456789', '60123456789'],
    ['01123456789', '601123456789'],
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

test('primaryWhatsAppNumber uses only user id 1', function () {
    User::factory()->create(['phone' => '60111111111']);
    User::factory()->create(['phone' => '60122222222']);

    expect(PhoneNumber::primaryWhatsAppNumber())->toBe('60111111111')
        ->and(PhoneNumber::isAllowedWhatsAppSender('60122222222'))->toBeFalse();
});

test('allowedWhatsAppSenderEntries lists only user id 1 under primary', function () {
    User::factory()->create([
        'name' => 'Admin User',
        'display_name' => 'Admin',
        'phone' => '60123456789',
    ]);
    User::factory()->create([
        'name' => 'Other User',
        'phone' => '60199999999',
    ]);
    $familyMember = FamilyMember::factory()->create([
        'name' => 'Spouse Full',
        'display_name' => 'Spouse',
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);

    $entries = PhoneNumber::allowedWhatsAppSenderEntries();

    expect($entries['primary'])->toHaveCount(1)
        ->and($entries['primary'][0])->toMatchArray([
            'name' => 'Admin User',
            'display_name' => 'Admin',
            'phone' => '60123456789',
        ])
        ->and($entries['primary'][0]['avatar_url'])->not->toBeEmpty()
        ->and($entries['family'])->toHaveCount(1)
        ->and($entries['family'][0])->toMatchArray([
            'id' => $familyMember->id,
            'name' => 'Spouse Full',
            'display_name' => 'Spouse',
            'phone' => '60111111111',
        ])
        ->and($entries['family'][0]['avatar_url'])->not->toBeEmpty();
});

test('allowedWhatsAppSenderEntries orders family members newest first', function () {
    User::factory()->create([
        'name' => 'Admin User',
        'phone' => '60123456789',
    ]);

    FamilyMember::factory()->create([
        'name' => 'Oldest Member',
        'phone' => '60111111111',
        'allowlist_enabled' => true,
        'created_at' => now()->subDays(3),
    ]);
    FamilyMember::factory()->create([
        'name' => 'Middle Member',
        'phone' => '60122222222',
        'allowlist_enabled' => true,
        'created_at' => now()->subDays(2),
    ]);
    FamilyMember::factory()->create([
        'name' => 'Newest Member',
        'phone' => '60133333333',
        'allowlist_enabled' => true,
        'created_at' => now()->subDay(),
    ]);

    $entries = PhoneNumber::allowedWhatsAppSenderEntries();

    expect($entries['family'])->toHaveCount(3)
        ->and(collect($entries['family'])->pluck('name')->all())->toBe([
            'Newest Member',
            'Middle Member',
            'Oldest Member',
        ]);
});

test('allowedWhatsAppSenderEntries uses uploaded avatars when set', function () {
    User::factory()->create([
        'name' => 'Admin User',
        'phone' => '60123456789',
        'avatar_url' => 'avatars/admin.png',
    ]);
    FamilyMember::factory()->create([
        'name' => 'Spouse',
        'phone' => '60111111111',
        'allowlist_enabled' => true,
        'avatar_url' => 'avatars/spouse.png',
    ]);

    $entries = PhoneNumber::allowedWhatsAppSenderEntries();

    expect($entries['primary'][0]['avatar_url'])->toContain('avatars/admin.png')
        ->and($entries['family'][0]['avatar_url'])->toContain('avatars/spouse.png');
});

test('allowedWhatsAppSenderEntries falls back to ui-avatars when avatar missing', function () {
    User::factory()->create([
        'name' => 'Admin User',
        'phone' => '60123456789',
        'avatar_url' => null,
    ]);
    FamilyMember::factory()->create([
        'name' => 'Spouse',
        'phone' => '60111111111',
        'allowlist_enabled' => true,
        'avatar_url' => null,
    ]);

    $entries = PhoneNumber::allowedWhatsAppSenderEntries();

    expect($entries['primary'][0]['avatar_url'])->toContain('ui-avatars.com')
        ->and($entries['family'][0]['avatar_url'])->toContain('ui-avatars.com');
});
