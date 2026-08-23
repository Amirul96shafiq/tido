<?php

declare(strict_types=1);

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Jobs\ExtractReceiptDataJob;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sends database notification with view and edit actions when expense requires manual review', function () {
    $user = User::factory()->create();

    $expense = Expense::factory()->create([
        'merchant_name' => 'Test Cafe',
        'original_filename' => 'lunch.jpg',
        'status' => 'parsed',
    ]);

    $expense->update(['status' => 'requires_manual_review']);

    $user->refresh();

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();

    $expectedViewUrl = ExpenseResource::getUrl('index', [
        'tableAction' => 'view',
        'tableActionRecord' => $expense->getRouteKey(),
    ]);

    expect($notification->data['title'])->toBe('Receipt requires manual review')
        ->and($notification->data['body'])->toContain('lunch.jpg')
        ->and($notification->data['body'])->toContain('Test Cafe')
        ->and($notification->data['actions'])->toHaveCount(2)
        ->and($notification->data['actions'][0]['name'])->toBe('view')
        ->and($notification->data['actions'][0]['label'])->toBe('View')
        ->and($notification->data['actions'][0]['url'])->toBe($expectedViewUrl)
        ->and($notification->data['actions'][0]['shouldOpenUrlInNewTab'])->toBeTrue()
        ->and($notification->data['actions'][1]['name'])->toBe('edit')
        ->and($notification->data['actions'][1]['label'])->toBe('Edit')
        ->and($notification->data['actions'][1]['url'])->toBe(ExpenseResource::getUrl('edit', ['record' => $expense]))
        ->and($notification->data['actions'][1]['shouldOpenUrlInNewTab'])->toBeTrue();
});

test('sends a specific database notification for non-receipt documents', function () {
    $user = User::factory()->create();

    $expense = Expense::factory()->create([
        'merchant_name' => 'Non-receipt document',
        'original_filename' => 'not-a-receipt.jpg',
        'status' => 'parsed',
        'document_classification' => Expense::DOCUMENT_CLASSIFICATION_RECEIPT,
    ]);

    $expense->update([
        'document_classification' => Expense::DOCUMENT_CLASSIFICATION_NOT_RECEIPT,
        'status' => 'requires_manual_review',
    ]);

    $notification = $user->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Non-receipt document requires manual review')
        ->and($notification->data['body'])->toContain('not-a-receipt.jpg')
        ->and($notification->data['body'])->toContain('does not appear to contain receipt information')
        ->and($notification->data['body'])->toContain('excluded from spending analytics')
        ->and($notification->data['actions'])->toHaveCount(2);
});

test('notifies only the primary admin when multiple users exist', function () {
    $primary = User::factory()->create(['phone' => '60111111111']);
    $other = User::factory()->create(['phone' => '60122222222']);

    $expense = Expense::factory()->create(['status' => 'parsed']);

    $expense->update(['status' => 'requires_manual_review']);

    expect($primary->fresh()->notifications()->count())->toBe(1)
        ->and($other->fresh()->notifications()->count())->toBe(0);
});

test('notifies the user matching whatsapp sender phone', function () {
    $primary = User::factory()->create(['phone' => '60111111111']);
    $senderUser = User::factory()->create(['phone' => '60133333333']);

    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'source' => 'whatsapp',
        'whatsapp_sender' => '60133333333',
    ]);

    $expense->update(['status' => 'requires_manual_review']);

    expect($senderUser->fresh()->notifications()->count())->toBe(1)
        ->and($primary->fresh()->notifications()->count())->toBe(0);
});

test('falls back to primary admin when whatsapp sender has no user account', function () {
    $primary = User::factory()->create(['phone' => '60111111111']);
    $other = User::factory()->create(['phone' => '60122222222']);

    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'source' => 'whatsapp',
        'whatsapp_sender' => '60199999999',
    ]);

    $expense->update(['status' => 'requires_manual_review']);

    expect($primary->fresh()->notifications()->count())->toBe(1)
        ->and($other->fresh()->notifications()->count())->toBe(0);
});

test('prefers primary admin when multiple users share the sender phone', function () {
    $primary = User::factory()->create(['phone' => '601116330705']);
    $duplicate = User::factory()->create(['phone' => '601116330705']);

    $expense = Expense::factory()->create([
        'status' => 'parsed',
        'source' => 'whatsapp',
        'whatsapp_sender' => '601116330705',
    ]);

    $expense->update(['status' => 'requires_manual_review']);

    expect($primary->fresh()->notifications()->count())->toBe(1)
        ->and($duplicate->fresh()->notifications()->count())->toBe(0);
});

test('does not notify when recipient has receipt review alerts disabled', function () {
    $user = User::factory()->create(['notify_receipt_review' => false]);

    $expense = Expense::factory()->create(['status' => 'parsed']);

    $expense->update(['status' => 'requires_manual_review']);

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('does not notify when status changes to a non-review status', function () {
    $user = User::factory()->create();

    $expense = Expense::factory()->create(['status' => 'parsed']);

    $expense->update(['status' => 'reviewed']);

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('extract job failed method sets requires_manual_review and notifies users', function () {
    $user = User::factory()->create();

    $expense = Expense::factory()->create(['status' => 'parsed']);

    $job = new ExtractReceiptDataJob($expense->id);
    $job->failed(new Exception('Ollama unavailable'));

    expect($expense->fresh()->status)->toBe('requires_manual_review')
        ->and($user->fresh()->notifications()->count())->toBe(1);
});

test('view notification cta opens expense list with view slide-over query params', function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());

    $expense = Expense::factory()->create(['status' => 'requires_manual_review']);

    $url = ExpenseResource::getUrl('index', [
        'tableAction' => 'view',
        'tableActionRecord' => $expense->getRouteKey(),
    ]);

    expect($url)
        ->toContain('tableAction=view')
        ->toContain('tableActionRecord='.$expense->getRouteKey());

    $this->get($url)->assertSuccessful();
});
