<?php

declare(strict_types=1);

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sends database notification with view and edit actions when invoice requires manual review', function () {
    $user = User::factory()->create();

    $invoice = Invoice::factory()->create([
        'merchant_name' => 'Test Cafe',
        'original_filename' => 'lunch.jpg',
        'status' => 'parsed',
    ]);

    $invoice->update(['status' => 'requires_manual_review']);

    $user->refresh();

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();

    $expectedViewUrl = InvoiceResource::getUrl('index', [
        'tableAction' => 'view',
        'tableActionRecord' => $invoice->getRouteKey(),
    ]);

    expect($notification->data['title'])->toBe('Receipt requires manual review')
        ->and($notification->data['body'])->toContain('lunch.jpg')
        ->and($notification->data['body'])->toContain('Test Cafe')
        ->and($notification->data['actions'])->toHaveCount(2)
        ->and($notification->data['actions'][0]['name'])->toBe('view')
        ->and($notification->data['actions'][0]['label'])->toBe('View')
        ->and($notification->data['actions'][0]['url'])->toBe($expectedViewUrl)
        ->and($notification->data['actions'][1]['name'])->toBe('edit')
        ->and($notification->data['actions'][1]['label'])->toBe('Edit')
        ->and($notification->data['actions'][1]['url'])->toBe(InvoiceResource::getUrl('edit', ['record' => $invoice]));
});

test('notifies all users when a receipt requires manual review', function () {
    $users = User::factory()->count(2)->create();

    $invoice = Invoice::factory()->create(['status' => 'parsed']);

    $invoice->update(['status' => 'requires_manual_review']);

    foreach ($users as $user) {
        expect($user->fresh()->notifications()->count())->toBe(1);
    }
});

test('does not notify when status changes to a non-review status', function () {
    $user = User::factory()->create();

    $invoice = Invoice::factory()->create(['status' => 'parsed']);

    $invoice->update(['status' => 'reviewed']);

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('extract job failed method sets requires_manual_review and notifies users', function () {
    $user = User::factory()->create();

    $invoice = Invoice::factory()->create(['status' => 'parsed']);

    $job = new ExtractReceiptDataJob($invoice->id);
    $job->failed(new Exception('Ollama unavailable'));

    expect($invoice->fresh()->status)->toBe('requires_manual_review')
        ->and($user->fresh()->notifications()->count())->toBe(1);
});

test('view notification cta opens invoice list with view slide-over query params', function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());

    $invoice = Invoice::factory()->create(['status' => 'requires_manual_review']);

    $url = InvoiceResource::getUrl('index', [
        'tableAction' => 'view',
        'tableActionRecord' => $invoice->getRouteKey(),
    ]);

    expect($url)
        ->toContain('tableAction=view')
        ->toContain('tableActionRecord='.$invoice->getRouteKey());

    $this->get($url)->assertSuccessful();
});
