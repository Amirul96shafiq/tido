<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Helpers\MoneyDisplay;
use App\Models\Recurring;
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
     *     statusLine: string
     * }
     */
    public static function forForm(Get $get, ?Recurring $record, string $operation): array
    {
        $title = filled($get('title')) ? (string) $get('title') : 'Untitled recurring';
        $type = RecurringType::tryFrom((string) ($get('type') ?? ''));

        return [
            'title' => $title,
            'amountLine' => self::amountLine($type, $get('expected_amount')),
            'scheduleLine' => self::scheduleLine($get),
            'nextDueLine' => self::nextDueLine($get, $record, $operation),
            'statusLine' => self::statusLine($get),
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

    private static function statusLine(Get $get): string
    {
        $active = ($get('is_active') ?? true) === true || ($get('is_active') ?? true) === 1;

        return $active ? 'Active' : 'Inactive';
    }
}
