<?php

declare(strict_types=1);

use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\FamilyMemberLoginService;
use App\Services\RecurringReminderService;
use App\Services\WhatsAppNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow('2026-08-17 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('family member can save recurring reminder preferences on profile', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60111111111',
    ]);
    $user = app(FamilyMemberLoginService::class)->syncLoginUser($member);

    expect($user)->not->toBeNull();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertFormFieldExists('notify_recurring_reminders')
        ->assertFormFieldExists('notify_receipt_review')
        ->assertFormFieldExists('recurring_reminder_lead_days')
        ->assertFormFieldExists('recurring_reminder_time')
        ->assertFormFieldIsHidden('notify_budget_alerts')
        ->assertFormFieldIsHidden('notify_evolution_api')
        ->assertFormFieldIsHidden('notify_service_status')
        ->assertFormFieldIsHidden('notify_backups')
        ->set('data.notify_recurring_reminders', true)
        ->set('data.recurring_reminder_lead_days', 3)
        ->set('data.recurring_reminder_time', '07:30')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->notify_recurring_reminders)->toBeTrue()
        ->and($user->notify_receipt_review)->toBeTrue()
        ->and($user->notify_service_status)->toBeFalse()
        ->and($user->notify_backups)->toBeFalse()
        ->and($user->recurring_reminder_lead_days)->toBe(3)
        ->and($user->recurringReminderTimeHi())->toBe('07:30');
});

test('recurring reminder helper text is present in html when the toggle is off', function () {
    $user = User::factory()->create([
        'notify_recurring_reminders' => false,
        'notify_email_digest' => false,
        'notify_budget_alerts' => true,
        'notify_profile_updates' => true,
    ]);

    $this->actingAs($user);

    $html = Livewire::test(EditProfile::class)->html();

    expect($html)
        ->toContain('Due and overdue reminders. In-app vs WhatsApp stays on each Recurring.')
        ->toContain('Coming soon — preference saved for future digest emails.')
        ->toContain('In-app inbox when spending exceeds a budget threshold.')
        ->toContain('Receipt Review')
        ->toContain('Service Status')
        ->toContain('Backup Alerts')
        ->toContain('fi-fieldset')
        ->toContain('fi-profile-toggle-field')
        ->toContain('flex-direction:column');
});

test('receipt review and backup alert preferences persist from profile', function () {
    $user = User::factory()->create([
        'notify_receipt_review' => true,
        'notify_backups' => true,
        'notify_service_status' => true,
        'notify_profile_updates' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSchemaComponentExists('notifications-finances')
        ->assertSchemaComponentExists('notifications-account')
        ->assertSchemaComponentExists('notifications-tools')
        ->assertSchemaComponentExists('notifications-coming-soon')
        ->set('data.notify_receipt_review', false)
        ->set('data.notify_backups', false)
        ->set('data.notify_service_status', false)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->notify_receipt_review)->toBeFalse()
        ->and($user->notify_backups)->toBeFalse()
        ->and($user->notify_service_status)->toBeFalse();
});

test('email digest toggle is disabled and is not saved from profile', function () {
    $user = User::factory()->create([
        'notify_email_digest' => false,
        'notify_profile_updates' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertFormFieldDisabled('notify_email_digest')
        ->set('data.notify_email_digest', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->notify_email_digest)->toBeFalse();
});

test('recurring reminder lead days and send time hide when toggle is off', function () {
    $user = User::factory()->create([
        'notify_recurring_reminders' => true,
        'notify_profile_updates' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertFormFieldIsVisible('recurring_reminder_lead_days')
        ->assertFormFieldIsVisible('recurring_reminder_time')
        ->assertSee('fi-nested-fields', false)
        ->set('data.notify_recurring_reminders', false)
        ->assertFormFieldIsHidden('recurring_reminder_lead_days')
        ->assertFormFieldIsHidden('recurring_reminder_time')
        ->assertDontSee('fi-nested-fields', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->notify_recurring_reminders)->toBeFalse();
});

test('updating recurring reminder preferences triggers profile update notification', function () {
    $user = User::factory()->create([
        'notify_recurring_reminders' => true,
        'recurring_reminder_lead_days' => 7,
        'recurring_reminder_time' => '08:00:00',
        'notify_profile_updates' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.recurring_reminder_lead_days', 2)
        ->set('data.recurring_reminder_time', '09:15')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->notifications()->count())->toBe(1);

    $body = $user->notifications()->first()->data['body'];
    expect($body)->toContain('Recurring reminder lead days')
        ->and($body)->toContain('Recurring reminder send time');
});

test('saving a past send time from profile skips reminders for the rest of today', function () {
    Carbon::setTestNow('2026-08-17 13:07:00');
    Cache::flush();

    $user = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_lead_days' => 7,
        'recurring_reminder_time' => '14:00:00',
        'notify_profile_updates' => false,
    ]);

    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldNotReceive('sendMessage');
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
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.recurring_reminder_time', '10:00')
        ->call('save')
        ->assertHasNoErrors();

    $result = app(RecurringReminderService::class)->sendDueReminders();

    expect($user->fresh()->recurringReminderTimeHi())->toBe('10:00')
        ->and($result['reminded'])->toBe(0)
        ->and($result['users'])->toBe(0)
        ->and($user->fresh()->notifications()->count())->toBe(0);
});

test('reminder service skips user when toggle is off', function () {
    $user = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => false,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
    ]);

    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldNotReceive('sendMessage');
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
    ]);

    $result = app(RecurringReminderService::class)->sendDueReminders();

    expect($result['reminded'])->toBe(0)
        ->and($result['users'])->toBe(0)
        ->and($user->fresh()->notifications()->count())->toBe(0);
});

test('reminder service skips before local send time and sends once after', function () {
    $user = User::factory()->create([
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
    ]);

    Carbon::setTestNow('2026-08-17 07:59:00');
    $before = app(RecurringReminderService::class)->sendDueReminders();

    expect($before['reminded'])->toBe(0)
        ->and($user->fresh()->notifications()->count())->toBe(0);

    Carbon::setTestNow('2026-08-17 08:00:00');
    $first = app(RecurringReminderService::class)->sendDueReminders();
    $second = app(RecurringReminderService::class)->sendDueReminders();

    expect($first['reminded'])->toBe(1)
        ->and($second['reminded'])->toBe(0)
        ->and($user->fresh()->notifications()->count())->toBe(1);
});

test('lead days zero skips future due and overdue still sends', function () {
    $user = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
        'recurring_reminder_lead_days' => 0,
    ]);

    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')->once()->andReturn(true);
    app()->instance(WhatsAppNotificationService::class, $wa);

    $dueToday = Recurring::factory()->create([
        'title' => 'Due Today',
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'family_member_id' => null,
        'is_shared' => false,
    ]);
    $future = Recurring::factory()->create([
        'title' => 'Future',
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'family_member_id' => null,
        'is_shared' => false,
    ]);
    $overdue = Recurring::factory()->create([
        'title' => 'Overdue',
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'family_member_id' => null,
        'is_shared' => false,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $dueToday->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => '2026-08-17',
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $future->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => '2026-08-20',
    ]);
    RecurringOccurrence::factory()->create([
        'recurring_id' => $overdue->id,
        'status' => RecurringOccurrenceStatus::Overdue,
        'due_on' => '2026-08-10',
    ]);

    $result = app(RecurringReminderService::class)->sendDueReminders();

    expect($result['reminded'])->toBe(2)
        ->and($user->fresh()->notifications()->count())->toBe(1);

    $notification = $user->fresh()->notifications()->first();
    expect($notification->data['title'])->toBe('Recurring payment summary')
        ->and($notification->data['status'])->toBe('danger')
        ->and($notification->data['body'])->toContain('Overdue')
        ->and($notification->data['body'])->toContain('Due Today')
        ->and($notification->data['body'])->not->toContain('Future');
});

test('primary and family at different times do not cross-send whatsapp', function () {
    $primary = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
        'recurring_reminder_lead_days' => 7,
    ]);

    $member = FamilyMember::factory()->loginEnabled()->create([
        'phone' => '60111111111',
    ]);
    $familyUser = app(FamilyMemberLoginService::class)->syncLoginUser($member);
    expect($familyUser)->not->toBeNull();
    $familyUser->update([
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '10:00:00',
        'recurring_reminder_lead_days' => 7,
    ]);

    $numbers = [];
    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')
        ->andReturnUsing(function (string $number, string $text) use (&$numbers): bool {
            $numbers[] = $number;

            return true;
        });
    app()->instance(WhatsAppNotificationService::class, $wa);

    $recurring = Recurring::factory()->create([
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => $member->id,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    Carbon::setTestNow('2026-08-17 08:05:00');
    app(RecurringReminderService::class)->sendDueReminders();

    expect($numbers)->toBe(['60123456789'])
        ->and($primary->fresh()->notifications()->count())->toBe(1)
        ->and($familyUser->fresh()->notifications()->count())->toBe(0);

    Carbon::setTestNow('2026-08-17 10:05:00');
    app(RecurringReminderService::class)->sendDueReminders();

    expect($numbers)->toBe(['60123456789', '60111111111'])
        ->and($familyUser->fresh()->notifications()->count())->toBe(1);
});

test('no-login family whatsapp follows primary clock', function () {
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

    $numbers = [];
    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')
        ->andReturnUsing(function (string $number) use (&$numbers): bool {
            $numbers[] = $number;

            return true;
        });
    app()->instance(WhatsAppNotificationService::class, $wa);

    $recurring = Recurring::factory()->create([
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'is_shared' => false,
        'family_member_id' => $member->id,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    Carbon::setTestNow('2026-08-17 07:50:00');
    app(RecurringReminderService::class)->sendDueReminders();
    expect($numbers)->toBe([]);

    Carbon::setTestNow('2026-08-17 08:05:00');
    app(RecurringReminderService::class)->sendDueReminders();

    expect($numbers)->toContain('60123456789')
        ->and($numbers)->toContain('60199998888');
});

test('template channel off skips that channel', function () {
    $user = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
    ]);

    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldNotReceive('sendMessage');
    app()->instance(WhatsAppNotificationService::class, $wa);

    $recurring = Recurring::factory()->create([
        'notify_filament' => true,
        'notify_whatsapp' => false,
        'is_shared' => false,
        'family_member_id' => null,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Due,
        'due_on' => now()->toDateString(),
    ]);

    $result = app(RecurringReminderService::class)->sendDueReminders();

    expect($result['reminded'])->toBe(1)
        ->and($user->fresh()->notifications()->count())->toBe(1);
});

test('upcoming inside lead window is reminded', function () {
    $user = User::factory()->create([
        'phone' => '60123456789',
        'notify_recurring_reminders' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'recurring_reminder_time' => '08:00:00',
        'recurring_reminder_lead_days' => 5,
    ]);

    $wa = Mockery::mock(WhatsAppNotificationService::class);
    $wa->shouldReceive('sendMessage')->once()->andReturn(true);
    app()->instance(WhatsAppNotificationService::class, $wa);

    $recurring = Recurring::factory()->create([
        'notify_filament' => true,
        'notify_whatsapp' => true,
        'family_member_id' => null,
        'is_shared' => false,
    ]);

    RecurringOccurrence::factory()->create([
        'recurring_id' => $recurring->id,
        'status' => RecurringOccurrenceStatus::Upcoming,
        'due_on' => '2026-08-20',
    ]);

    $result = app(RecurringReminderService::class)->sendDueReminders();

    expect($result['reminded'])->toBe(1)
        ->and($user->fresh()->notifications()->count())->toBe(1);
});
