<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\User;
use App\Support\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('primaryAdmin returns user id 1', function () {
    $primary = User::factory()->create(['phone' => '60111111111']);
    User::factory()->create(['phone' => '60122222222']);

    expect(NotificationRecipient::primaryAdmin()?->is($primary))->toBeTrue();
});

test('forPhone prefers primary when multiple users share a phone', function () {
    $primary = User::factory()->create(['phone' => '601116330705']);
    User::factory()->create(['phone' => '601116330705']);

    expect(NotificationRecipient::forPhone('601116330705')?->is($primary))->toBeTrue();
});

test('forPhone returns matching non-primary user when unique', function () {
    User::factory()->create(['phone' => '60111111111']);
    $other = User::factory()->create(['phone' => '60133333333']);

    expect(NotificationRecipient::forPhone('60133333333')?->is($other))->toBeTrue();
});

test('forPhone falls back to primary when no match', function () {
    $primary = User::factory()->create(['phone' => '60111111111']);

    expect(NotificationRecipient::forPhone('60199999999')?->is($primary))->toBeTrue();
});

test('forInvoice uses whatsapp sender phone', function () {
    User::factory()->create(['phone' => '60111111111']);
    $sender = User::factory()->create(['phone' => '60144444444']);

    $invoice = Expense::factory()->create([
        'whatsapp_sender' => '60144444444',
        'source' => 'whatsapp',
    ]);

    expect(NotificationRecipient::forInvoice($invoice)?->is($sender))->toBeTrue();
});

test('forInvoice falls back to primary when invoice has no whatsapp sender', function () {
    $primary = User::factory()->create(['phone' => '60111111111']);

    $invoice = Expense::factory()->create([
        'whatsapp_sender' => null,
        'source' => 'manual',
    ]);

    expect(NotificationRecipient::forInvoice($invoice)?->is($primary))->toBeTrue();
});

test('findUsersByPhone matches plus-prefix and local forms', function () {
    $user = User::factory()->create(['phone' => '60123456789']);

    expect(NotificationRecipient::findUsersByPhone('0123456789')->pluck('id')->all())
        ->toContain($user->id)
        ->and(NotificationRecipient::findUsersByPhone('+60123456789')->pluck('id')->all())
        ->toContain($user->id);
});
