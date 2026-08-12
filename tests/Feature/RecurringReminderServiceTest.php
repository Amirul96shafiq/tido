<?php

declare(strict_types=1);

use App\Enums\RecurringOccurrenceStatus;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\RecurringReminderService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('sends filament and whatsapp reminders once per due day', function () {
    Cache::flush();

    $primary = User::factory()->create([
        'phone' => '60123456789',
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
