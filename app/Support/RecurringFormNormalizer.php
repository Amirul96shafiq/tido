<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Models\Recurring;
use Carbon\Carbon;

final class RecurringFormNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data, ?Recurring $record = null): array
    {
        $data = self::normalizeCadence($data, $record);
        $data = self::normalizeEndRule($data);
        $data = self::normalizeResponsibility($data);
        $data = self::normalizeCommitmentsByType($data);
        $data = self::stripUiOnlyKeys($data);

        if ($record === null && empty($data['next_due_on'])) {
            $preview = new Recurring($data);
            $data['next_due_on'] = $preview->resolveInitialDueOn()?->toDateString();
        }

        if ($record !== null) {
            // Edit preserves persisted next_due_on; Adjust action is the only mutator.
            unset($data['next_due_on']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateVirtualFields(array $data, ?Recurring $record = null): array
    {
        $data['cadence_preset'] = self::presetFromData($data, $record);
        $data['end_rule'] = filled($data['ends_on'] ?? null) ? 'end_on_date' : 'ongoing';
        $data['responsibility'] = self::responsibilityFromData($data);
        $data['tracking_mode'] = self::trackingModeFromData($data);

        if (($data['cadence_preset'] ?? null) === 'once') {
            $data['due_date'] = $data['next_due_on']
                ?? $data['starts_on']
                ?? $record?->next_due_on?->toDateString()
                ?? $record?->starts_on?->toDateString();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeCadence(array $data, ?Recurring $record = null): array
    {
        $preset = $data['cadence_preset'] ?? null;

        if ($preset === 'once' || ($data['frequency'] ?? null) === RecurringFrequency::Once->value) {
            $dueDate = $data['due_date'] ?? $data['starts_on'] ?? null;

            $data['frequency'] = RecurringFrequency::Once->value;
            $data['interval_months'] = null;
            $data['ends_on'] = null;

            if (filled($dueDate)) {
                $due = Carbon::parse((string) $dueDate)->startOfDay();
                $data['starts_on'] = $due->toDateString();
                $data['anchor_day'] = min(28, max(1, (int) $due->day));

                if ($record === null) {
                    $data['next_due_on'] = $due->toDateString();
                }
            }

            return $data;
        }

        $data['frequency'] = RecurringFrequency::Repeating->value;

        if ($preset !== null) {
            $data['interval_months'] = match ($preset) {
                'quarterly' => 3,
                'semiannual' => 6,
                'yearly' => 12,
                'custom' => max(1, min(24, (int) ($data['interval_months'] ?? 1))),
                default => 1,
            };
        } elseif (empty($data['interval_months'])) {
            $data['interval_months'] = 1;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeEndRule(array $data): array
    {
        if (($data['frequency'] ?? null) === RecurringFrequency::Once->value) {
            $data['ends_on'] = null;

            return $data;
        }

        $endRule = $data['end_rule'] ?? (filled($data['ends_on'] ?? null) ? 'end_on_date' : 'ongoing');

        if ($endRule !== 'end_on_date') {
            $data['ends_on'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeResponsibility(array $data): array
    {
        $responsibility = $data['responsibility'] ?? null;

        if ($responsibility === null) {
            if (($data['is_shared'] ?? false) === true || ($data['is_shared'] ?? false) === 1) {
                $responsibility = 'household_shared';
            } elseif (filled($data['family_member_id'] ?? null)) {
                $responsibility = 'family_member';
            } else {
                $responsibility = 'primary';
            }
        }

        return match ($responsibility) {
            'family_member' => [
                ...$data,
                'is_shared' => false,
            ],
            'household_shared' => [
                ...$data,
                'family_member_id' => null,
                'is_shared' => true,
            ],
            default => [
                ...$data,
                'family_member_id' => null,
                'is_shared' => false,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeCommitmentsByType(array $data): array
    {
        $type = RecurringType::tryFrom((string) ($data['type'] ?? ''));

        if ($type === null || ! in_array($type, [
            RecurringType::DebtInstalment,
            RecurringType::TransferInvestment,
        ], true)) {
            $data['goal_target_amount'] = null;
            $data['instalment_total'] = null;
            $data['instalment_remaining'] = null;

            return $data;
        }

        if ($type === RecurringType::DebtInstalment) {
            $data['goal_target_amount'] = null;

            return $data;
        }

        $trackingMode = $data['tracking_mode'] ?? self::trackingModeFromData($data);

        return match ($trackingMode) {
            'target_amount' => [
                ...$data,
                // Keep goal; instalment counts may be derived on the model.
            ],
            'fixed_transfers' => [
                ...$data,
                'goal_target_amount' => null,
            ],
            default => [
                ...$data,
                'goal_target_amount' => null,
                'instalment_total' => null,
                'instalment_remaining' => null,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function stripUiOnlyKeys(array $data): array
    {
        unset(
            $data['cadence_preset'],
            $data['end_rule'],
            $data['responsibility'],
            $data['tracking_mode'],
            $data['due_date'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function presetFromData(array $data, ?Recurring $record = null): string
    {
        $frequency = $data['frequency'] ?? $record?->frequency?->value;

        if ($frequency === RecurringFrequency::Once->value || $frequency === RecurringFrequency::Once) {
            return 'once';
        }

        $interval = (int) ($data['interval_months'] ?? $record?->interval_months ?? 1);

        return match ($interval) {
            1 => 'monthly',
            3 => 'quarterly',
            6 => 'semiannual',
            12 => 'yearly',
            default => 'custom',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function responsibilityFromData(array $data): string
    {
        if (($data['is_shared'] ?? false) === true || ($data['is_shared'] ?? false) === 1) {
            return 'household_shared';
        }

        if (filled($data['family_member_id'] ?? null)) {
            return 'family_member';
        }

        return 'primary';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function trackingModeFromData(array $data): string
    {
        if (filled($data['goal_target_amount'] ?? null) && (float) $data['goal_target_amount'] > 0) {
            return 'target_amount';
        }

        if (filled($data['instalment_total'] ?? null)) {
            return 'fixed_transfers';
        }

        return 'open_ended';
    }
}
