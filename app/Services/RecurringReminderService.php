<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurringOccurrenceStatus;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Helpers\MoneyDisplay;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppPublicUrl;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
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
                if (! $this->isHouseholdReminderRecipient($user)) {
                    return;
                }

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
        if (! $this->isHouseholdReminderRecipient($user) || ! $user->notify_recurring_reminders) {
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
        if (! $this->isHouseholdReminderRecipient($user) || ! $user->notify_recurring_reminders) {
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
     * @return int Number of occurrence reminders included in dispatched summaries
     */
    public function sendPassForUser(User $user, ?CarbonInterface $now = null): int
    {
        $reference = $now?->copy() ?? now();
        $local = $reference->copy()->timezone($user->preferredTimezone());
        $localDate = $local->toDateString();
        $leadDays = max(0, min(14, (int) $user->recurring_reminder_lead_days));
        $leadEnd = $local->copy()->addDays($leadDays)->toDateString();

        $entries = $this->buildEntriesForUser($user, $localDate, $leadEnd);
        $reminded = $this->dispatchSummaryForUser($user, $entries);

        if ($this->isHouseholdPrimary($user)) {
            $reminded += $this->sendNoLoginFamilyWhatsAppSummaries(
                $localDate,
                $leadEnd,
                $user->recurringReminderTimeHi(),
            );
        }

        return $reminded;
    }

    /**
     * @return list<array{
     *     occurrence: RecurringOccurrence,
     *     title: string,
     *     amount: string,
     *     due_on: string,
     *     is_overdue: bool,
     *     notify_whatsapp: bool,
     *     notify_filament: bool
     * }>
     */
    private function buildEntriesForUser(User $user, string $localDate, string $leadEnd): array
    {
        $entries = [];

        foreach ($this->occurrencesForUser($user, $leadEnd)->get() as $occurrence) {
            $recurring = $occurrence->recurring;

            if (! $recurring instanceof Recurring || ! $recurring->is_active) {
                continue;
            }

            if (! $recurring->notify_whatsapp && ! $recurring->notify_filament) {
                continue;
            }

            if (! $this->claimReminderSlot($user, $occurrence, $localDate)) {
                continue;
            }

            $entries[] = $this->makeEntry($occurrence, $recurring, $localDate);
        }

        return $this->sortEntries($entries);
    }

    /**
     * @param  list<array{
     *     occurrence: RecurringOccurrence,
     *     title: string,
     *     amount: string,
     *     due_on: string,
     *     is_overdue: bool,
     *     notify_whatsapp: bool,
     *     notify_filament: bool
     * }>  $entries
     */
    private function dispatchSummaryForUser(User $user, array $entries): int
    {
        if ($entries === []) {
            return 0;
        }

        $whatsAppEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['notify_whatsapp'],
        ));
        $filamentEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['notify_filament'],
        ));

        $whatsAppSent = false;
        $filamentSent = false;

        if ($whatsAppEntries !== []) {
            $number = PhoneNumber::normalize(is_string($user->phone) ? $user->phone : null);

            if ($number !== null) {
                $whatsAppSent = $this->waService->sendMessage(
                    $number,
                    $this->whatsAppSummaryMessage($whatsAppEntries),
                );
            }
        }

        if ($filamentEntries !== []) {
            $this->sendFilamentSummary($user, $filamentEntries);
            $filamentSent = true;
        }

        return $this->markRemindedOccurrences($entries, $whatsAppSent, $filamentSent);
    }

    /**
     * WhatsApp-only summary nudges for assigned family members without panel login,
     * sent on the Primary user's schedule.
     */
    private function sendNoLoginFamilyWhatsAppSummaries(
        string $localDate,
        string $leadEnd,
        string $primarySendAt,
    ): int {
        $grouped = [];

        foreach ($this->occurrencesForPrimaryNoLoginFamily($leadEnd)->get() as $occurrence) {
            $recurring = $occurrence->recurring;

            if (! $recurring instanceof Recurring || ! $recurring->notify_whatsapp) {
                continue;
            }

            $owner = $recurring->familyMember;

            if (
                ! $owner instanceof FamilyMember
                || ! $owner->allowlist_enabled
                || ! filled($owner->phone)
                || $owner->login_enabled
            ) {
                continue;
            }

            $number = PhoneNumber::normalize((string) $owner->phone);

            if ($number === null) {
                continue;
            }

            $cacheUserKey = 'family:'.$owner->getKey();

            if (! $this->claimReminderSlotKey(
                $cacheUserKey,
                $occurrence,
                $localDate,
                $primarySendAt,
            )) {
                continue;
            }

            $grouped[$owner->getKey()]['number'] = $number;
            $grouped[$owner->getKey()]['entries'][] = $this->makeEntry($occurrence, $recurring, $localDate);
        }

        $reminded = 0;

        foreach ($grouped as $bundle) {
            $entries = $this->sortEntries($bundle['entries']);

            if ($entries === []) {
                continue;
            }

            $sent = $this->waService->sendMessage(
                $bundle['number'],
                $this->whatsAppSummaryMessage($entries),
            );

            if (! $sent) {
                continue;
            }

            foreach ($entries as $entry) {
                $entry['occurrence']->reminded_at = now();
                $entry['occurrence']->save();
                $reminded++;
            }
        }

        return $reminded;
    }

    /**
     * @return array{
     *     occurrence: RecurringOccurrence,
     *     title: string,
     *     amount: string,
     *     due_on: string,
     *     is_overdue: bool,
     *     notify_whatsapp: bool,
     *     notify_filament: bool
     * }
     */
    private function makeEntry(
        RecurringOccurrence $occurrence,
        Recurring $recurring,
        string $localDate,
    ): array {
        $isOverdue = $occurrence->due_on->toDateString() < $localDate
            || $occurrence->status === RecurringOccurrenceStatus::Overdue;

        return [
            'occurrence' => $occurrence,
            'title' => filled($recurring->title) ? (string) $recurring->title : 'Untitled',
            'amount' => $occurrence->expected_amount !== null
                ? MoneyDisplay::withPrefix($occurrence->expected_amount)
                : 'variable',
            'due_on' => $occurrence->due_on->format('d M Y'),
            'is_overdue' => $isOverdue,
            'notify_whatsapp' => (bool) $recurring->notify_whatsapp,
            'notify_filament' => (bool) $recurring->notify_filament,
        ];
    }

    /**
     * @param  list<array{
     *     occurrence: RecurringOccurrence,
     *     title: string,
     *     amount: string,
     *     due_on: string,
     *     is_overdue: bool,
     *     notify_whatsapp: bool,
     *     notify_filament: bool
     * }>  $entries
     * @return list<array{
     *     occurrence: RecurringOccurrence,
     *     title: string,
     *     amount: string,
     *     due_on: string,
     *     is_overdue: bool,
     *     notify_whatsapp: bool,
     *     notify_filament: bool
     * }>
     */
    private function sortEntries(array $entries): array
    {
        usort($entries, static function (array $left, array $right): int {
            if ($left['is_overdue'] !== $right['is_overdue']) {
                return $left['is_overdue'] ? -1 : 1;
            }

            return $left['occurrence']->due_on->toDateString()
                <=> $right['occurrence']->due_on->toDateString();
        });

        return $entries;
    }

    /**
     * @param  list<array{
     *     occurrence: RecurringOccurrence,
     *     title: string,
     *     amount: string,
     *     due_on: string,
     *     is_overdue: bool,
     *     notify_whatsapp: bool,
     *     notify_filament: bool
     * }>  $entries
     */
    private function whatsAppSummaryMessage(array $entries): string
    {
        $indexUrl = WhatsAppPublicUrl::withRoot(
            fn (): string => RecurringResource::getUrl('index'),
        );

        return WhatsAppMessage::recurringReminderSummary(
            $indexUrl,
            array_map(static fn (array $entry): array => [
                'title' => $entry['title'],
                'amount' => $entry['amount'],
                'due_on' => $entry['due_on'],
                'is_overdue' => $entry['is_overdue'],
            ], $entries),
        );
    }

    /**
     * @param  list<array{
     *     occurrence: RecurringOccurrence,
     *     title: string,
     *     amount: string,
     *     due_on: string,
     *     is_overdue: bool,
     *     notify_whatsapp: bool,
     *     notify_filament: bool
     * }>  $entries
     */
    private function sendFilamentSummary(User $user, array $entries): void
    {
        $hasOverdue = collect($entries)->contains(
            static fn (array $entry): bool => $entry['is_overdue'],
        );

        $lines = [];

        foreach ($entries as $entry) {
            $dueLabel = $entry['is_overdue'] ? 'Overdue' : 'Due';
            $lines[] = sprintf(
                '%s · %s · %s %s',
                $entry['title'],
                $entry['amount'],
                $dueLabel,
                $entry['due_on'],
            );
        }

        $count = count($entries);
        $body = sprintf(
            "%d payment%s in your reminder window.\n\n%s",
            $count,
            $count === 1 ? '' : 's',
            implode("\n", $lines),
        );

        $notification = FilamentNotification::make()
            ->title('Recurring payment summary')
            ->body($body)
            ->actions([
                Action::make('viewRecurrings')
                    ->label('View recurrings')
                    ->button()
                    ->url(RecurringResource::getUrl('index'), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ]);

        if ($hasOverdue) {
            $notification->danger();
        } else {
            $notification->warning();
        }

        $notification->sendToDatabase($user);
    }

    /**
     * @param  list<array{
     *     occurrence: RecurringOccurrence,
     *     title: string,
     *     amount: string,
     *     due_on: string,
     *     is_overdue: bool,
     *     notify_whatsapp: bool,
     *     notify_filament: bool
     * }>  $entries
     */
    private function markRemindedOccurrences(array $entries, bool $whatsAppSent, bool $filamentSent): int
    {
        $reminded = 0;

        foreach ($entries as $entry) {
            $included = ($whatsAppSent && $entry['notify_whatsapp'])
                || ($filamentSent && $entry['notify_filament']);

            if (! $included) {
                continue;
            }

            $entry['occurrence']->reminded_at = now();
            $entry['occurrence']->save();
            $reminded++;
        }

        return $reminded;
    }

    /**
     * Recurring WhatsApp/inbox passes are for the Profile owner (user id 1)
     * and linked family login users only — leftover factory Primaries are ignored.
     */
    private function isHouseholdReminderRecipient(User $user): bool
    {
        if ($this->isHouseholdPrimary($user)) {
            return true;
        }

        return $user->isFamilyMember() && $user->family_member_id !== null;
    }

    private function isHouseholdPrimary(User $user): bool
    {
        $primary = PhoneNumber::primaryUser();

        return $user->isPrimary()
            && $primary instanceof User
            && $primary->is($user);
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
                            ->where('allowlist_enabled', true)
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
