<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurringOccurrenceStatus;
use App\Helpers\MoneyDisplay;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Support\NotificationRecipient;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Cache;

class RecurringReminderService
{
    public function __construct(protected WhatsAppNotificationService $waService) {}

    /**
     * @return array{reminded: int}
     */
    public function sendDueReminders(): array
    {
        $reminded = 0;

        RecurringOccurrence::query()
            ->with(['recurring.familyMember.loginUser'])
            ->whereIn('status', [
                RecurringOccurrenceStatus::Due,
                RecurringOccurrenceStatus::Overdue,
            ])
            ->whereHas('recurring', fn ($query) => $query->active())
            ->orderBy('due_on')
            ->each(function (RecurringOccurrence $occurrence) use (&$reminded): void {
                if ($this->remind($occurrence)) {
                    $reminded++;
                }
            });

        return ['reminded' => $reminded];
    }

    public function remind(RecurringOccurrence $occurrence): bool
    {
        $recurring = $occurrence->recurring;

        if (! $recurring instanceof Recurring || ! $recurring->is_active) {
            return false;
        }

        if (! $this->claimReminderSlot($occurrence)) {
            return false;
        }

        $isOverdue = $occurrence->status === RecurringOccurrenceStatus::Overdue;
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

        if ($recurring->notify_whatsapp) {
            $this->sendWhatsApp($recurring, $heading, $body, $isOverdue);
        }

        if ($recurring->notify_filament) {
            $this->sendFilament($recurring, $heading, $body, $isOverdue);
        }

        $occurrence->reminded_at = now();
        $occurrence->save();

        return true;
    }

    private function sendWhatsApp(Recurring $recurring, string $heading, string $body, bool $isOverdue): void
    {
        $message = WhatsAppMessage::compose(
            $isOverdue ? '⏰' : '📅',
            $heading,
            $body,
        );

        $numbers = [];
        $primaryNumber = PhoneNumber::primaryWhatsAppNumber();

        if ($primaryNumber !== null) {
            $numbers[$primaryNumber] = true;
        }

        // Shared templates: Primary only (v1). Owned templates also notify the family member.
        if (! $recurring->is_shared) {
            $owner = $recurring->familyMember;

            if ($owner instanceof FamilyMember && filled($owner->phone)) {
                $normalized = PhoneNumber::normalize((string) $owner->phone);

                if ($normalized !== null) {
                    $numbers[$normalized] = true;
                }
            }
        }

        foreach (array_keys($numbers) as $number) {
            $this->waService->sendMessage((string) $number, $message);
        }
    }

    private function sendFilament(
        Recurring $recurring,
        string $heading,
        string $body,
        bool $isOverdue,
    ): void {
        $recipients = [];

        $primary = NotificationRecipient::primaryAdmin();

        if ($primary instanceof User) {
            $recipients[$primary->getKey()] = $primary;
        }

        if (! $recurring->is_shared) {
            $ownerUser = $recurring->familyMember?->loginUser;

            if (
                $ownerUser instanceof User
                && $recurring->familyMember?->login_enabled
            ) {
                $recipients[$ownerUser->getKey()] = $ownerUser;
            }
        }

        foreach ($recipients as $user) {
            $notification = FilamentNotification::make()
                ->title($heading)
                ->body($body);

            if ($isOverdue) {
                $notification->danger();
            } else {
                $notification->warning();
            }

            $notification->sendToDatabase($user);
        }
    }

    private function claimReminderSlot(RecurringOccurrence $occurrence): bool
    {
        $cacheKey = sprintf(
            'recurring-reminder:%d:%s',
            $occurrence->id,
            $occurrence->due_on->toDateString(),
        );

        return Cache::add($cacheKey, true, now()->addDay());
    }
}
