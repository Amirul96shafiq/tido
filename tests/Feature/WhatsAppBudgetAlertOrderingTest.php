<?php

declare(strict_types=1);

use App\Jobs\SendDeferredWhatsAppBudgetAlertJob;
use App\Jobs\SendWhatsAppDocumentParsedJob;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Label;
use App\Models\User;
use App\Services\BudgetAlertService;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppDocumentReceivedDebouncer;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.instance' => 'tido',
    ]);

    Http::fake([
        '*/message/sendText/*' => Http::response(['status' => 'success']),
    ]);
});

test('whatsapp parsed status does not send budget alert synchronously from observer', function () {
    User::factory()->create(['phone' => '60123456789']);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'is_active' => true,
    ]);

    $invoice = Invoice::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'pending',
        'date_time' => now(),
        'total_amount' => 90,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    $invoice->update(['status' => 'parsed']);

    Http::assertNothingSent();
    $this->assertDatabaseCount('notifications', 0);
});

test('document parsed job queues deferred budget alert after whatsapp reply', function () {
    Queue::fake([SendDeferredWhatsAppBudgetAlertJob::class]);

    User::factory()->create(['phone' => '60123456789']);

    $invoice = Invoice::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'merchant_name' => 'Cafe',
        'total_amount' => 12.5,
    ]);

    Cache::forget(WhatsAppDocumentReceivedDebouncer::cacheKey('60123456789'));

    (new SendWhatsAppDocumentParsedJob($invoice->id))->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], 'Document parsed'));

    Queue::assertPushed(SendDeferredWhatsAppBudgetAlertJob::class, function (SendDeferredWhatsAppBudgetAlertJob $job) use ($invoice): bool {
        return $job->senderNumber === '60123456789'
            && $job->invoiceId === $invoice->id;
    });
});

test('deferred budget alert waits while sender still has pending whatsapp invoices', function () {
    User::factory()->create(['phone' => '60123456789']);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'is_active' => true,
    ]);

    $parsed = Invoice::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'date_time' => now(),
        'total_amount' => 90,
    ]);

    InvoiceItem::create([
        'invoice_id' => $parsed->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    Invoice::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'pending',
    ]);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(3);
    $queueJob->shouldReceive('attempts')->andReturn(1);

    $job = new SendDeferredWhatsAppBudgetAlertJob('60123456789', $parsed->id);
    $job->setJob($queueJob);
    $job->handle(app(BudgetAlertService::class));

    Http::assertNothingSent();
    $this->assertDatabaseCount('notifications', 0);
});

test('deferred budget alert sends after all sender pending invoices are gone', function () {
    User::factory()->create(['phone' => '60123456789']);

    $label = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
    ]);

    Budget::create([
        'label_id' => $label->id,
        'amount' => 100.00,
        'period' => 'monthly',
        'year' => (int) now()->year,
        'alert_threshold' => 80,
        'is_active' => true,
    ]);

    $parsed = Invoice::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'date_time' => now(),
        'total_amount' => 90,
    ]);

    InvoiceItem::create([
        'invoice_id' => $parsed->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    (new SendDeferredWhatsAppBudgetAlertJob('60123456789', $parsed->id))
        ->handle(app(BudgetAlertService::class));

    Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], 'Budget alert'));
    $this->assertDatabaseCount('notifications', 1);
});
