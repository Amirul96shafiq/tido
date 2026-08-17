<?php

declare(strict_types=1);

use App\Enums\RecurringOccurrenceStatus;
use App\Models\FamilyMember;
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

    $notification = $primary->fresh()->notifications()->first();
    expect($notification->data['title'])->toBe('Recurring payment summary')
        ->and($notification->data['status'])->toBe('warning')
        ->and($notification->data['actions'][0]['label'])->toBe('View recurrings');
});

test('aggregates multiple occurrences into one whatsapp and one inbox summary', function () {
    $primary = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
        'recurring_reminder_lead_days' => 7,
    ]);

    $messages = [];
    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')
        ->once()
        ->andReturnUsing(function (string $number, string $text) use (&$messages): bool {
            $messages[] = ['number' => $number, 'text' => $text];

            return true;
        });
    app()->instance(WhatsAppNotificationService::class, $wa);

    $overdue = Recurring::factory()->create([
        'title' => 'Home Financing-i',
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => null,
    ]);
    $later = Recurring::factory()->create([
        'title' => 'Tabung Raya 2027',
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => null,
    ]);
    $sooner = Recurring::factory()->create([
        'title' => 'Internet',
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $later->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => '2026-08-24',
        'expected_amount' => 50,
        'reminded_at' => null,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $overdue->id,
        'status' => RecurringOccurrenceStatus::Overdue,
        'due_on' => '2026-08-10',
        'expected_amount' => 1327,
        'reminded_at' => null,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $sooner->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => '2026-08-20',
        'expected_amount' => 99,
        'reminded_at' => null,
    ]);

    $result = app(RecurringReminderService::class)->sendDueReminders();

    expect($result['reminded'])->toBe(3)
        ->and($messages)->toHaveCount(1)
        ->and($messages[0]['number'])->toBe('60123456789')
        ->and($messages[0]['text'])->toContain('*Recurring payment summary*')
        ->and($messages[0]['text'])->toContain('*Home Financing-i*')
        ->and($messages[0]['text'])->toContain('*Internet*')
        ->and($messages[0]['text'])->toContain('*Tabung Raya 2027*')
        ->and($messages[0]['text'])->toContain('View recurrings')
        ->and($primary->fresh()->notifications()->count())->toBe(1);

    $body = $primary->fresh()->notifications()->first()->data['body'];
    $overduePos = strpos($body, 'Home Financing-i');
    $internetPos = strpos($body, 'Internet');
    $tabungPos = strpos($body, 'Tabung Raya 2027');

    expect($primary->fresh()->notifications()->first()->data['status'])->toBe('danger')
        ->and($overduePos)->toBeLessThan($internetPos)
        ->and($internetPos)->toBeLessThan($tabungPos);
});

test('splits summary channels by template toggles', function () {
    $primary = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
        'recurring_reminder_lead_days' => 7,
    ]);

    $messages = [];
    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')
        ->once()
        ->andReturnUsing(function (string $number, string $text) use (&$messages): bool {
            $messages[] = $text;

            return true;
        });
    app()->instance(WhatsAppNotificationService::class, $wa);

    $whatsAppOnly = Recurring::factory()->create([
        'title' => 'WhatsApp Only Bill',
        'notify_filament' => false,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => null,
    ]);
    $inboxOnly = Recurring::factory()->create([
        'title' => 'Inbox Only Bill',
        'notify_filament' => true,
        'notify_whatsapp' => false,
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $whatsAppOnly->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'reminded_at' => null,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $inboxOnly->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
        'reminded_at' => null,
    ]);

    $result = app(RecurringReminderService::class)->sendDueReminders();

    expect($result['reminded'])->toBe(2)
        ->and($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('WhatsApp Only Bill')
        ->and($messages[0])->not->toContain('Inbox Only Bill')
        ->and($primary->fresh()->notifications()->count())->toBe(1);

    $notification = $primary->fresh()->notifications()->first();
    expect($notification->data['body'])->toContain('Inbox Only Bill')
        ->and($notification->data['body'])->not->toContain('WhatsApp Only Bill')
        ->and($notification->data['status'])->toBe('warning');
});

test('no-login family receives one whatsapp summary for all assigned items', function () {
    User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
        'recurring_reminder_lead_days' => 7,
    ]);

    $member = FamilyMember::factory()->create([
        'phone' => '60199998888',
        'login_enabled' => false,
    ]);

    $familyMessages = [];
    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')
        ->andReturnUsing(function (string $number, string $text) use (&$familyMessages): bool {
            if ($number === '60199998888') {
                $familyMessages[] = $text;
            }

            return true;
        });
    app()->instance(WhatsAppNotificationService::class, $wa);

    $first = Recurring::factory()->create([
        'title' => 'Family Phone',
        'notify_filament' => false,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => $member->id,
    ]);
    $second = Recurring::factory()->create([
        'title' => 'Family Streaming',
        'notify_filament' => false,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => $member->id,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $first->id,
        'status' => RecurringOccurrenceStatus::Overdue,
        'due_on' => '2026-08-10',
        'reminded_at' => null,
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $second->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => '2026-08-17',
        'reminded_at' => null,
    ]);

    $result = app(RecurringReminderService::class)->sendDueReminders();

    expect($result['reminded'])->toBeGreaterThanOrEqual(2)
        ->and($familyMessages)->toHaveCount(1)
        ->and($familyMessages[0])->toContain('*Recurring payment summary*')
        ->and($familyMessages[0])->toContain('*Family Phone*')
        ->and($familyMessages[0])->toContain('*Family Streaming*');
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
