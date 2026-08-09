<?php

declare(strict_types=1);

use App\Jobs\SendDeferredWhatsAppBudgetAlertJob;
use App\Jobs\SendWhatsAppDocumentParsedJob;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseItem;
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

    $expense = Expense::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'pending',
        'date_time' => now(),
        'total_amount' => 90,
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    $expense->update(['status' => 'parsed']);

    Http::assertNothingSent();
    $this->assertDatabaseCount('notifications', 0);
});

test('document parsed job queues deferred budget alert after whatsapp reply', function () {
    Queue::fake([SendDeferredWhatsAppBudgetAlertJob::class]);

    User::factory()->create(['phone' => '60123456789']);

    $expense = Expense::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'merchant_name' => 'Cafe',
        'total_amount' => 12.5,
    ]);

    Cache::forget(WhatsAppDocumentReceivedDebouncer::cacheKey('60123456789'));

    (new SendWhatsAppDocumentParsedJob($expense->id))->handle(app(WhatsAppNotificationService::class));

    Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], 'Document parsed'));

    Queue::assertPushed(SendDeferredWhatsAppBudgetAlertJob::class, function (SendDeferredWhatsAppBudgetAlertJob $job) use ($expense): bool {
        return $job->senderNumber === '60123456789'
            && $job->expenseId === $expense->id;
    });
});

test('deferred budget alert waits while sender still has pending whatsapp expenses', function () {
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

    $parsed = Expense::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'date_time' => now(),
        'total_amount' => 90,
    ]);

    ExpenseItem::create([
        'expense_id' => $parsed->id,
        'label_id' => $label->id,
        'description' => 'Burgers',
        'quantity' => 1,
        'unit_price' => 90.00,
        'line_total' => 90.00,
    ]);

    Expense::factory()->create([
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

test('deferred budget alert sends after all sender pending expenses are gone', function () {
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

    $parsed = Expense::factory()->create([
        'source' => 'whatsapp',
        'whatsapp_sender' => '60123456789',
        'status' => 'parsed',
        'date_time' => now(),
        'total_amount' => 90,
    ]);

    ExpenseItem::create([
        'expense_id' => $parsed->id,
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
