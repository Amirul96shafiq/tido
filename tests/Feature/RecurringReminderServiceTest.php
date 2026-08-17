<?php

declare(strict_types=1);

use App\Enums\RecurringOccurrenceStatus;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\RecurringReminderService;
use App\Services\WhatsAppNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow('2026-08-17 08:05:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('sends filament and whatsapp reminders once per due day for opted-in user', function () {
    $primary = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
        'recurring_reminder_lead_days' => 7,
    ]);

    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')->once()->andReturn(true);
    app()->instance(WhatsAppNotificationService::class, $wa);

    $recurring = Recurring::factory()->create([
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'reminded_at' => null,
    ]);

    $service = app(RecurringReminderService::class);
    $first = $service->sendDueReminders();
    $second = $service->sendDueReminders();

    expect($first['reminded'])->toBe(1)
        ->and($second['reminded'])->toBe(0)
        ->and($primary->fresh()->notifications()->count())->toBe(1);
});

test('changing send time allows another reminder pass the same day', function () {
    $primary = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
        'recurring_reminder_lead_days' => 7,
    ]);

    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')->twice()->andReturn(true);
    app()->instance(WhatsAppNotificationService::class, $wa);

    $recurring = Recurring::factory()->create([
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'reminded_at' => null,
    ]);

    $service = app(RecurringReminderService::class);
    $first = $service->sendDueReminders();

    $primary->update(['recurring_reminder_time' => '13:00:00']);
    Carbon::setTestNow('2026-08-17 13:05:00');

    $second = $service->sendDueReminders();
    $third = $service->sendDueReminders();

    expect($first['reminded'])->toBe(1)
        ->and($second['reminded'])->toBe(1)
        ->and($third['reminded'])->toBe(0)
        ->and($primary->fresh()->notifications()->count())->toBe(2);
});

test('changing send time to a past clock time skips today and waits for tomorrow', function () {
    Carbon::setTestNow('2026-08-17 13:07:00');

    $primary = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '14:00:00',
        'recurring_reminder_lead_days' => 7,
    ]);

    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')->once()->andReturn(true);
    app()->instance(WhatsAppNotificationService::class, $wa);

    $recurring = Recurring::factory()->create([
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'reminded_at' => null,
    ]);

    $service = app(RecurringReminderService::class);

    $primary->update(['recurring_reminder_time' => '10:00:00']);
    $suppressed = $service->suppressTodayPassIfSendTimePassed($primary->fresh());

    $sameDay = $service->sendDueReminders();

    Carbon::setTestNow('2026-08-18 10:00:00');
    $nextDay = $service->sendDueReminders();

    expect($suppressed)->toBeTrue()
        ->and($sameDay['reminded'])->toBe(0)
        ->and($sameDay['users'])->toBe(0)
        ->and($nextDay['reminded'])->toBe(1)
        ->and($primary->fresh()->notifications()->count())->toBe(1);
});
