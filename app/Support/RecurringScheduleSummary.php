<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\HouseholdRole;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Helpers\MoneyDisplay;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Models\User;
use Carbon\Carbon;
use Filament\Schemas\Components\Utilities\Get;

final class RecurringScheduleSummary
{
    /**
     * @return array{
     *     title: string,
     *     amountLine: string,
     *     scheduleLine: string,
     *     nextDueLine: string,
     *     labelLine: string,
     *     ownershipLine: string,
     *     statusLine: string
     * }
     */
    public static function forForm(Get $get, ?Recurring $record, string $operation): array
    {
        $title = filled($get('title')) ? (string) $get('title') : 'Untitled recurring';
        $type = RecurringType::tryFrom((string) ($get('type') ?? ''));
        $amountRaw = $get('expected_amount');
        $amountLine = self::amountLine($type, $amountRaw);
        $scheduleLine = self::scheduleLine($get);
        $nextDueLine = self::nextDueLine($get, $record, $operation);
        $labelLine = self::labelLine($get('label_id'));
        $ownershipLine = self::ownershipLine($get);
        $statusLine = self::statusLine($get);

        return [
            'title' => $title,
            'amountLine' => $amountLine,
            'scheduleLine' => $scheduleLine,
            'nextDueLine' => $nextDueLine,
            'labelLine' => $labelLine,
            'ownershipLine' => $ownershipLine,
            'statusLine' => $statusLine,
        ];
    }

    public static function cadenceLabel(?string $preset, ?int $intervalMonths = null): string
    {
        return match ($preset) {
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'semiannual' => 'Every 6 months',
            'yearly' => 'Yearly',
            'custom' => 'Every '.max(1, (int) ($intervalMonths ?? 1)).' months',
            'once' => 'Once',
            default => 'Schedule incomplete',
        };
    }

    private static function amountLine(?RecurringType $type, mixed $amountRaw): string
    {
        $typeLabel = $type?->label() ?? 'Type unset';

        if ($amountRaw === null || $amountRaw === '') {
            if ($type === RecurringType::VariableBill) {
                return 'Variable · '.$typeLabel;
            }

            return 'Amount unset · '.$typeLabel;
        }

        return MoneyDisplay::withPrefix($amountRaw).' · '.$typeLabel;
    }

    private static function scheduleLine(Get $get): string
    {
        $preset = (string) ($get('cadence_preset') ?? 'monthly');

        if ($preset === 'once') {
            $dueDate = $get('due_date');

            if (! filled($dueDate)) {
                return 'Once · Due date unset';
            }

            return 'Once on '.Carbon::parse((string) $dueDate)->format('j M Y');
        }

        $cadence = self::cadenceLabel($preset, is_numeric($get('interval_months')) ? (int) $get('interval_months') : null);
        $anchorDay = $get('anchor_day');

        if (! filled($anchorDay)) {
            return $cadence.' · Due day unset';
        }

        return $cadence.' on day '.(int) $anchorDay;
    }

    private static function nextDueLine(Get $get, ?Recurring $record, string $operation): string
    {
        if ($operation === 'edit' || $operation === 'view') {
            $persisted = $record?->next_due_on;

            if ($persisted === null) {
                return 'Next due: —';
            }

            return 'Next due: '.$persisted->format('j M Y');
        }

        $preview = self::previewNextDueOn($get);

        if ($preview === null) {
            return 'Next due: —';
        }

        return 'Next due: '.$preview->format('j M Y');
    }

    private static function previewNextDueOn(Get $get): ?Carbon
    {
        $preset = (string) ($get('cadence_preset') ?? 'monthly');

        if ($preset === 'once') {
            $dueDate = $get('due_date');

            return filled($dueDate) ? Carbon::parse((string) $dueDate)->startOfDay() : null;
        }

        $startsOn = $get('starts_on');
        $anchorDay = $get('anchor_day');

        if (! filled($startsOn) || ! filled($anchorDay)) {
            return null;
        }

        $interval = match ($preset) {
            'quarterly' => 3,
            'semiannual' => 6,
            'yearly' => 12,
            'custom' => max(1, min(24, (int) ($get('interval_months') ?? 1))),
            default => 1,
        };

        $preview = new Recurring([
            'frequency' => RecurringFrequency::Repeating->value,
            'interval_months' => $interval,
            'anchor_day' => (int) $anchorDay,
            'starts_on' => (string) $startsOn,
        ]);

        return $preview->resolveInitialDueOn();
    }

    private static function labelLine(mixed $labelId): string
    {
        if (! filled($labelId)) {
            return 'No label';
        }

        $name = Label::query()->whereKey($labelId)->value('name');

        return filled($name) ? (string) $name : 'No label';
    }

    private static function ownershipLine(Get $get): string
    {
        $responsibility = (string) ($get('responsibility') ?? 'primary');
        $owner = match ($responsibility) {
            'family_member' => self::familyMemberName($get('family_member_id')),
            'household_shared' => 'Household shared',
            default => self::primaryUsername(),
        };

        $channels = [];

        if (($get('notify_filament') ?? true) === true || ($get('notify_filament') ?? true) === 1) {
            $channels[] = 'In-app';
        }

        if (($get('notify_whatsapp') ?? true) === true || ($get('notify_whatsapp') ?? true) === 1) {
            $channels[] = 'WhatsApp';
        }

        $channelLine = $channels === [] ? 'No reminders' : implode(' + ', $channels);

        return $owner.' · '.$channelLine;
    }

    private static function statusLine(Get $get): string
    {
        $active = ($get('is_active') ?? true) === true || ($get('is_active') ?? true) === 1;

        return $active ? 'Active' : 'Inactive';
    }

    private static function familyMemberName(mixed $familyMemberId): string
    {
        if (! filled($familyMemberId)) {
            return 'Family member unset';
        }

        $member = FamilyMember::query()->find($familyMemberId);

        if (! $member instanceof FamilyMember) {
            return 'Family member unset';
        }

        return filled($member->display_name)
            ? (string) $member->display_name
            : (string) $member->name;
    }

    private static function primaryUsername(): string
    {
        $primary = User::query()
            ->where('household_role', HouseholdRole::Primary)
            ->orderBy('id')
            ->first();

        if ($primary === null) {
            return 'Primary';
        }

        return filled($primary->display_name)
            ? (string) $primary->display_name
            : (string) $primary->name;
    }
}
