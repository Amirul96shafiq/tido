<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurringOccurrenceStatus;
use App\Helpers\MoneyDisplay;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class RecurringReminderService
{
    public function __construct(protected WhatsAppNotificationService $waService) {}

    /**
     * @return array{reminded: int, users: int}
     */
    public function sendDueReminders(?CarbonInterface $now = null): array
    {
        $reminded = 0;
        $usersProcessed = 0;
        $reference = $now?->copy() ?? now();

        User::query()
            ->where('notify_recurring_reminders', true)
            ->orderBy('id')
            ->each(function (User $user) use (&$reminded, &$usersProcessed, $reference): void {
                if (! $this->shouldRunPassForUser($user, $reference)) {
                    return;
                }

                $sent = $this->sendPassForUser($user, $reference);
                $reminded += $sent;
                $usersProcessed++;
                $this->claimPassSlot($user, $reference);
            });

        return [
            'reminded' => $reminded,
            'users' => $usersProcessed,
        ];
    }

    public function shouldRunPassForUser(User $user, ?CarbonInterface $now = null): bool
    {
        if (! $user->notify_recurring_reminders) {
            return false;
        }

        $reference = $now?->copy() ?? now();
        $local = $reference->copy()->timezone($user->preferredTimezone());
        $localDate = $local->toDateString();

        if (! $this->passSlotAvailable($user, $localDate)) {
            return false;
        }

        $sendAt = $user->recurringReminderTimeHi();
        $localTime = $local->format('H:i');

        return $localTime >= $sendAt;
    }

    /**
     * When Profile sets a send time that is already past today, claim today's
     * pass without sending so the next real send is tomorrow at that time.
     * Catch-up for an unchanged schedule still works via sendDueReminders().
     */
    public function suppressTodayPassIfSendTimePassed(User $user, ?CarbonInterface $now = null): bool
    {
        if (! $user->notify_recurring_reminders) {
            return false;
        }

        $reference = $now?->copy() ?? now();
        $local = $reference->copy()->timezone($user->preferredTimezone());
        $localDate = $local->toDateString();
        $sendAt = $user->recurringReminderTimeHi();
        $localTime = $local->format('H:i');

        // Strictly past — exact current minute may still send via the scheduler.
        if ($localTime <= $sendAt) {
            return false;
        }

        if (! $this->passSlotAvailable($user, $localDate)) {
            return false;
        }

        $this->claimPassSlot($user, $reference);

        return true;
    }

    /**
     * @return int Number of occurrence reminders sent for this user
     */
    public function sendPassForUser(User $user, ?CarbonInterface $now = null): int
    {
        $reference = $now?->copy() ?? now();
        $local = $reference->copy()->timezone($user->preferredTimezone());
        $localDate = $local->toDateString();
        $leadDays = max(0, min(14, (int) $user->recurring_reminder_lead_days));
        $leadEnd = $local->copy()->addDays($leadDays)->toDateString();

        $reminded = 0;

        $this->occurrencesForUser($user, $leadEnd)
            ->each(function (RecurringOccurrence $occurrence) use ($user, $localDate, &$reminded): void {
                if ($this->remindUser($user, $occurrence, $localDate)) {
                    $reminded++;
                }
            });

        if ($user->isPrimary()) {
            $reminded += $this->sendNoLoginFamilyWhatsAppPass(
                $localDate,
                $leadEnd,
                $user->recurringReminderTimeHi(),
            );
        }

        return $reminded;
    }

    public function remindUser(User $user, RecurringOccurrence $occurrence, string $localDate): bool
    {
        $recurring = $occurrence->recurring;

        if (! $recurring instanceof Recurring || ! $recurring->is_active) {
            return false;
        }

        if (! $recurring->notify_whatsapp && ! $recurring->notify_filament) {
            return false;
        }

        if (! $this->claimReminderSlot($user, $occurrence, $localDate)) {
            return false;
        }

        $isOverdue = $occurrence->due_on->toDateString() < $localDate
            || $occurrence->status === RecurringOccurrenceStatus::Overdue;
        $amount = $occurrence->expected_amount !== null
            ? MoneyDisplay::withPrefix($occurrence->expected_amount)
            : 'variable';
        $dueOn = $occurrence->due_on->format('d M Y');

        $heading = $isOverdue ? 'Recurring payment overdue' : 'Recurring payment due';
        $body = sprintf(
            '%s · %s · due %s',
            $recurring->title,
            $amount,
            $dueOn,
        );

        $sent = false;

        if ($recurring->notify_whatsapp) {
            $number = PhoneNumber::normalize(is_string($user->phone) ? $user->phone : null);

            if ($number !== null) {
                $this->waService->sendMessage(
                    $number,
                    WhatsAppMessage::compose(
                        $isOverdue ? '⏰' : '📅',
                        $heading,
                        $body,
                    ),
                );
                $sent = true;
            }
        }

        if ($recurring->notify_filament) {
            $notification = FilamentNotification::make()
                ->title($heading)
                ->body($body);

            if ($isOverdue) {
                $notification->danger();
            } else {
                $notification->warning();
            }

            $notification->sendToDatabase($user);
            $sent = true;
        }

        if ($sent) {
            $occurrence->reminded_at = now();
            $occurrence->save();
        }

        return $sent;
    }

    /**
     * WhatsApp-only nudges for assigned family members without panel login,
     * sent on the Primary user's schedule.
     */
    private function sendNoLoginFamilyWhatsAppPass(
        string $localDate,
        string $leadEnd,
        string $primarySendAt,
    ): int {
        $reminded = 0;

        $this->occurrencesForPrimaryNoLoginFamily($leadEnd)
            ->each(function (RecurringOccurrence $occurrence) use ($localDate, $primarySendAt, &$reminded): void {
                $recurring = $occurrence->recurring;

                if (! $recurring instanceof Recurring || ! $recurring->notify_whatsapp) {
                    return;
                }

                $owner = $recurring->familyMember;

                if (! $owner instanceof FamilyMember || ! filled($owner->phone) || $owner->login_enabled) {
                    return;
                }

                $number = PhoneNumber::normalize((string) $owner->phone);

                if ($number === null) {
                    return;
                }

                $cacheUserKey = 'family:'.$owner->getKey();

                if (! $this->claimReminderSlotKey(
                    $cacheUserKey,
                    $occurrence,
                    $localDate,
                    $primarySendAt,
                )) {
                    return;
                }

                $isOverdue = $occurrence->due_on->toDateString() < $localDate
                    || $occurrence->status === RecurringOccurrenceStatus::Overdue;
                $amount = $occurrence->expected_amount !== null
                    ? MoneyDisplay::withPrefix($occurrence->expected_amount)
                    : 'variable';
                $dueOn = $occurrence->due_on->format('d M Y');
                $heading = $isOverdue ? 'Recurring payment overdue' : 'Recurring payment due';
                $body = sprintf(
                    '%s · %s · due %s',
                    $recurring->title,
                    $amount,
                    $dueOn,
                );

                $this->waService->sendMessage(
                    $number,
                    WhatsAppMessage::compose(
                        $isOverdue ? '⏰' : '📅',
                        $heading,
                        $body,
                    ),
                );

                $occurrence->reminded_at = now();
                $occurrence->save();
                $reminded++;
            });

        return $reminded;
    }

    /**
     * @return Builder<RecurringOccurrence>
     */
    private function occurrencesForUser(User $user, string $leadEnd): Builder
    {
        return RecurringOccurrence::query()
            ->with(['recurring.familyMember.loginUser'])
            ->open()
            ->whereDate('due_on', '<=', $leadEnd)
            ->whereHas('recurring', function (Builder $query) use ($user): void {
                $query->active();

                if ($user->isFamilyMember()) {
                    $query
                        ->where('family_member_id', $user->family_member_id)
                        ->where('is_shared', false);
                }
            })
            ->orderBy('due_on');
    }

    /**
     * @return Builder<RecurringOccurrence>
     */
    private function occurrencesForPrimaryNoLoginFamily(string $leadEnd): Builder
    {
        return RecurringOccurrence::query()
            ->with(['recurring.familyMember.loginUser'])
            ->open()
            ->whereDate('due_on', '<=', $leadEnd)
            ->whereHas('recurring', function (Builder $query): void {
                $query
                    ->active()
                    ->where('notify_whatsapp', true)
                    ->whereNotNull('family_member_id')
                    ->where('is_shared', false)
                    ->whereHas('familyMember', function (Builder $member): void {
                        $member
                            ->where('login_enabled', false)
                            ->whereNotNull('phone');
                    });
            })
            ->orderBy('due_on');
    }

    private function passSlotAvailable(User $user, string $localDate): bool
    {
        return ! Cache::has($this->passCacheKey($user, $localDate));
    }

    private function claimPassSlot(User $user, CarbonInterface $reference): void
    {
        $local = $reference->copy()->timezone($user->preferredTimezone());
        $localDate = $local->toDateString();
        $ttl = max(60, $local->copy()->endOfDay()->getTimestamp() - $local->getTimestamp());

        Cache::put($this->passCacheKey($user, $localDate), true, $ttl);
    }

    private function claimReminderSlot(User $user, RecurringOccurrence $occurrence, string $localDate): bool
    {
        return $this->claimReminderSlotKey(
            (string) $user->getKey(),
            $occurrence,
            $localDate,
            $user->recurringReminderTimeHi(),
        );
    }

    private function claimReminderSlotKey(
        string $userKey,
        RecurringOccurrence $occurrence,
        string $localDate,
        string $scheduleToken,
    ): bool {
        $cacheKey = sprintf(
            'recurring-reminder:%s:%d:%s:%s',
            $userKey,
            $occurrence->id,
            $localDate,
            $scheduleToken,
        );

        return Cache::add($cacheKey, true, now()->addDay());
    }

    private function passCacheKey(User $user, string $localDate): string
    {
        // Include send-at so changing Profile schedule can still fire the same day.
        return sprintf(
            'recurring-reminder-pass:%d:%s:%s',
            $user->getKey(),
            $localDate,
            $user->recurringReminderTimeHi(),
        );
    }
}
