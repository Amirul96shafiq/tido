<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\HouseholdRole;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Enums\RecurringType;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Helpers\MoneyDisplay;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\RecurringOccurrenceGenerator;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Schemas\Components\Utilities\Get;

final class DueRecurringPreview
{
    /**
     * @return array{
     *     hasData: bool,
     *     emptyHeading?: string,
     *     emptyDescription?: string,
     *     canManageRecurrings: bool,
     *     canReorderRecurrings: bool,
     *     items: list<array<string, mixed>>,
     *     manageUrl: string,
     *     titleIndicator: 'alert'|'calm',
     *     totalCount: int
     * }
     */
    public static function forForm(?Recurring $record, Get $get, string $operation): array
    {
        $manageUrl = RecurringResource::getUrl('index');
        $empty = [
            'hasData' => false,
            'emptyHeading' => 'Set a title and due day to preview',
            'emptyDescription' => 'Enter a title and due day under Details and Schedule to see how this recurring would appear on Recurring Payment Dues.',
            'canManageRecurrings' => true,
            'canReorderRecurrings' => true,
            'items' => [],
            'manageUrl' => $manageUrl,
            'titleIndicator' => 'calm',
            'totalCount' => 0,
        ];

        $title = $get('title');

        if (! filled($title)) {
            return $empty;
        }

        $dueOn = $operation === 'create'
            ? RecurringScheduleSummary::previewNextDueOn($get)
            : ($record?->nextOpenDueOn() ?? RecurringScheduleSummary::previewNextDueOn($get));

        if ($dueOn === null) {
            return $empty;
        }

        $status = app(RecurringOccurrenceGenerator::class)->statusForDueOn($dueOn->copy()->startOfDay());
        $preview = self::overlayFormRecurring($record, $get);
        $owner = self::ownerProfile($preview, self::primaryUser());
        $label = self::previewLabel($get('label_id') ?? $record?->label_id);
        $amount = MoneyDisplay::parse($get('expected_amount'));
        $goal = self::goalPreview($record, $preview, $get);
        $cadencePreset = filled($get('cadence_preset')) ? (string) $get('cadence_preset') : null;
        $isActionable = in_array($status, [
            RecurringOccurrenceStatus::Due,
            RecurringOccurrenceStatus::Overdue,
        ], true);

        $item = [
            'id' => 'preview',
            'recurring_id' => $record?->getKey(),
            'can_reorder' => true,
            'is_completed' => false,
            'is_skipped' => false,
            'edit_url' => null,
            'title' => (string) $title,
            'owner_avatar_url' => $owner['avatar_url'],
            'owner_name' => $owner['name'],
            'icon' => filled($label?->icon) ? (string) $label->icon : 'heroicon-o-arrow-path',
            'color' => filled($label?->color) ? (string) $label->color : '#FFD07D',
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'dueOn' => $dueOn->format('d M Y'),
            'completedAt' => null,
            'amount' => $amount !== null ? MoneyDisplay::withPrefix($amount) : 'Variable',
            'type' => $preview->type instanceof RecurringType ? $preview->type->label() : '',
            'cadence' => $cadencePreset !== null
                ? RecurringScheduleSummary::cadenceLabel(
                    $cadencePreset,
                    is_numeric($get('interval_months')) ? (int) $get('interval_months') : $preview->interval_months,
                )
                : self::cadenceFromRecurring($preview),
            'is_shared' => (bool) $preview->is_shared,
            'progress' => $goal['progress'],
            'progressAmount' => $goal['progressAmount'],
            'goalTarget' => $goal['goalTarget'],
        ];

        return [
            'hasData' => true,
            'canManageRecurrings' => true,
            'canReorderRecurrings' => true,
            'items' => [$item],
            'manageUrl' => $manageUrl,
            'titleIndicator' => $isActionable ? 'alert' : 'calm',
            'totalCount' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemFromOccurrence(
        RecurringOccurrence $occurrence,
        bool $isPrimary,
        ?User $primaryUser,
    ): array {
        $recurring = $occurrence->recurring;
        $label = $recurring?->label;
        $owner = $recurring !== null
            ? self::ownerProfile($recurring, $primaryUser)
            : [
                'avatar_url' => app(UiAvatarsProvider::class)->get(new User(['name' => 'Primary'])),
                'name' => 'Primary',
            ];
        $isCompleted = $occurrence->status === RecurringOccurrenceStatus::Completed;
        $isSkipped = $occurrence->status === RecurringOccurrenceStatus::Skipped;
        $displayAmount = $isCompleted
            ? ($occurrence->actual_amount ?? $occurrence->expected_amount)
            : $occurrence->expected_amount;
        $completedAt = $occurrence->expense?->date_time ?? $occurrence->updated_at;

        return [
            'id' => $occurrence->id,
            'recurring_id' => $recurring?->id,
            'can_reorder' => $isPrimary && $recurring !== null && ! $isCompleted && ! $isSkipped,
            'is_completed' => $isCompleted,
            'is_skipped' => $isSkipped,
            'edit_url' => $recurring !== null && HouseholdAccess::canMutateRecurring($recurring)
                ? RecurringResource::getUrl('edit', ['record' => $recurring])
                : null,
            'title' => $recurring?->title ?? 'Recurring',
            'owner_avatar_url' => $owner['avatar_url'],
            'owner_name' => $owner['name'],
            'icon' => $label?->icon ?: 'heroicon-o-arrow-path',
            'color' => $label?->color ?: '#FFD07D',
            'status' => $occurrence->status->value,
            'statusLabel' => $occurrence->status->label(),
            'dueOn' => $occurrence->due_on->format('d M Y'),
            'completedAt' => $completedAt?->format('d M Y H:i'),
            'amount' => $displayAmount !== null
                ? MoneyDisplay::withPrefix($displayAmount)
                : 'Variable',
            'type' => $recurring?->type->label() ?? '',
            'cadence' => self::cadenceFromRecurring($recurring),
            'is_shared' => (bool) ($recurring?->is_shared ?? false),
            'progress' => $recurring?->goalProgressPercent(),
            'progressAmount' => $recurring?->goalProgressAmount(),
            'goalTarget' => $recurring?->goal_target_amount !== null
                ? (float) $recurring->goal_target_amount
                : null,
        ];
    }

    public static function cadenceFromRecurring(?Recurring $recurring): string
    {
        if ($recurring === null) {
            return '';
        }

        if ($recurring->frequency === RecurringFrequency::Once) {
            return 'Once';
        }

        $months = (int) ($recurring->interval_months ?? 1);

        return match ($months) {
            1 => 'Monthly',
            3 => 'Quarterly',
            6 => 'Every 6 months',
            12 => 'Yearly',
            default => "Every {$months} months",
        };
    }

    public static function primaryUser(): ?User
    {
        return User::query()
            ->where(function ($query): void {
                $query
                    ->where('household_role', HouseholdRole::Primary->value)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{avatar_url: string, name: string}
     */
    public static function ownerProfile(Recurring $recurring, ?User $primaryUser): array
    {
        $familyMember = $recurring->familyMember;

        if ($familyMember instanceof FamilyMember) {
            return [
                'avatar_url' => self::avatarDisplayUrl($familyMember),
                'name' => filled($familyMember->display_name)
                    ? (string) $familyMember->display_name
                    : (string) $familyMember->name,
            ];
        }

        if ($primaryUser instanceof User) {
            return [
                'avatar_url' => self::avatarDisplayUrl($primaryUser),
                'name' => filled($primaryUser->display_name)
                    ? (string) $primaryUser->display_name
                    : (string) $primaryUser->name,
            ];
        }

        return [
            'avatar_url' => app(UiAvatarsProvider::class)->get(new User(['name' => 'Primary'])),
            'name' => 'Primary',
        ];
    }

    public static function avatarDisplayUrl(User|FamilyMember $record): string
    {
        $url = $record->getFilamentAvatarUrl();

        if ($url !== null) {
            return $url;
        }

        return app(UiAvatarsProvider::class)->get($record);
    }

    private static function overlayFormRecurring(?Recurring $record, Get $get): Recurring
    {
        $preview = $record instanceof Recurring ? $record->replicate() : new Recurring;

        $type = RecurringType::tryFrom((string) ($get('type') ?? ''));
        if ($type instanceof RecurringType) {
            $preview->type = $type;
        }

        $responsibility = (string) ($get('responsibility') ?? 'primary');
        $familyMemberId = $get('family_member_id');

        if ($responsibility === 'household_shared') {
            $preview->is_shared = true;
            $preview->family_member_id = null;
            $preview->unsetRelation('familyMember');
        } elseif ($responsibility === 'family_member' && filled($familyMemberId)) {
            $preview->is_shared = false;
            $preview->family_member_id = (int) $familyMemberId;
            $preview->unsetRelation('familyMember');
            $preview->loadMissing('familyMember');
        } else {
            $preview->is_shared = false;
            $preview->family_member_id = null;
            $preview->unsetRelation('familyMember');
        }

        $labelId = $get('label_id');
        $preview->label_id = filled($labelId) ? (int) $labelId : null;

        $expectedAmount = MoneyDisplay::parse($get('expected_amount'));
        $preview->expected_amount = $expectedAmount;

        $goalTarget = MoneyDisplay::parse($get('goal_target_amount'));
        $preview->goal_target_amount = $goalTarget;

        $preview->prior_contributed_amount = self::resolvedPriorAmount($get);
        $preview->loadMissing('label');

        return $preview;
    }

    /**
     * @return array{progress: float|null, progressAmount: float|null, goalTarget: float|null}
     */
    private static function goalPreview(?Recurring $record, Recurring $preview, Get $get): array
    {
        $showsGoal = $preview->type === RecurringType::TransferInvestment
            && ($get('tracking_mode') ?? 'open_ended') === 'target_amount';

        $goalTarget = $showsGoal ? MoneyDisplay::parse($get('goal_target_amount')) : null;

        if ($goalTarget === null || $goalTarget <= 0) {
            return [
                'progress' => null,
                'progressAmount' => null,
                'goalTarget' => null,
            ];
        }

        $prior = self::resolvedPriorAmount($get);
        $completed = 0.0;

        if ($record instanceof Recurring) {
            $completed = (float) $record->occurrences()
                ->where('status', RecurringOccurrenceStatus::Completed)
                ->sum('actual_amount');
        }

        $progressAmount = round($prior + $completed, 2);

        return [
            'progress' => min(100, round(($progressAmount / $goalTarget) * 100, 2)),
            'progressAmount' => $progressAmount,
            'goalTarget' => $goalTarget,
        ];
    }

    private static function resolvedPriorAmount(Get $get): float
    {
        $mode = (string) ($get('prior_contribution_mode') ?? 'none');

        if ($mode === 'count') {
            $count = max(0, (int) ($get('prior_transfer_count') ?? 0));
            $expectedAmount = MoneyDisplay::parse($get('expected_amount')) ?? 0.0;

            return $count > 0 && $expectedAmount > 0
                ? round($count * $expectedAmount, 2)
                : 0.0;
        }

        if ($mode === 'amount') {
            return max(0.0, MoneyDisplay::parse($get('prior_contributed_amount')) ?? 0.0);
        }

        return 0.0;
    }

    private static function previewLabel(mixed $labelId): ?Label
    {
        if (blank($labelId)) {
            return null;
        }

        $label = Label::query()->find($labelId);

        return $label instanceof Label ? $label : null;
    }
}
