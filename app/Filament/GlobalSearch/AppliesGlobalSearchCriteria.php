<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

use App\Enums\HouseholdRole;
use App\Enums\LabelType;
use App\Enums\RecurringType;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AppliesGlobalSearchCriteria
{
    public static function applyToExpenseQuery(Builder $query): void
    {
        $criteria = GlobalSearchCriteria::instance();

        if (! $criteria->includes(GlobalSearchType::Expenses)) {
            return;
        }

        $filters = $criteria->activeFilters();

        if (! $criteria->isOnly(GlobalSearchType::Expenses)) {
            self::applyExpenseSort($query, $criteria->sort());

            return;
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        if (filled($filters['source'] ?? null)) {
            $query->where('source', (string) $filters['source']);
        }

        if (filled($filters['family_member_id'] ?? null)) {
            $query->where('family_member_id', (int) $filters['family_member_id']);
        }

        if (filled($filters['payment_method_id'] ?? null)) {
            $query->where('payment_method_id', (int) $filters['payment_method_id']);
        }

        if (filled($filters['label_id'] ?? null)) {
            $labelId = (int) $filters['label_id'];
            $query->whereHas('expenseItems', fn (Builder $itemQuery): Builder => $itemQuery->where('label_id', $labelId));
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('date_time', '>=', Carbon::parse((string) $filters['from'])->toDateString());
        }

        if (filled($filters['until'] ?? null)) {
            $query->whereDate('date_time', '<=', Carbon::parse((string) $filters['until'])->toDateString());
        }

        if (filled($filters['total_min'] ?? null)) {
            $query->where('total_amount', '>=', (float) $filters['total_min']);
        }

        if (filled($filters['total_max'] ?? null)) {
            $query->where('total_amount', '<=', (float) $filters['total_max']);
        }

        self::applyExpenseSort($query, $criteria->sort());
    }

    public static function applyToBudgetQuery(Builder $query): void
    {
        $criteria = GlobalSearchCriteria::instance();

        if (! $criteria->isOnly(GlobalSearchType::Budgets)) {
            return;
        }

        $filters = $criteria->activeFilters();

        if (filled($filters['period'] ?? null)) {
            $query->where('period', (string) $filters['period']);
        }

        if (filled($filters['family_member_id'] ?? null)) {
            if ($filters['family_member_id'] === 'primary') {
                $query->whereNull('family_member_id');
            } else {
                $query->where('family_member_id', (int) $filters['family_member_id']);
            }
        }

        if (filled($filters['is_shared'] ?? null)) {
            $query->where('is_shared', self::booleanFilterValue($filters['is_shared']));
        }

        if (filled($filters['is_active'] ?? null)) {
            $query->where('is_active', self::booleanFilterValue($filters['is_active']));
        }

        self::applyBudgetSort($query, $criteria->sort());
    }

    public static function applyToRecurringQuery(Builder $query): void
    {
        $criteria = GlobalSearchCriteria::instance();

        if (! $criteria->isOnly(GlobalSearchType::Recurrings)) {
            return;
        }

        $filters = $criteria->activeFilters();

        if (filled($filters['type'] ?? null)) {
            $type = RecurringType::tryFrom((string) $filters['type']);

            if ($type instanceof RecurringType) {
                $query->where('type', $type->value);
            }
        }

        if (filled($filters['is_active'] ?? null)) {
            $query->where('is_active', self::booleanFilterValue($filters['is_active']));
        }

        if (filled($filters['is_shared'] ?? null)) {
            $query->where('is_shared', self::booleanFilterValue($filters['is_shared']));
        }

        self::applyRecurringSort($query, $criteria->sort());
    }

    public static function applyToLabelQuery(Builder $query): void
    {
        $criteria = GlobalSearchCriteria::instance();

        if (! $criteria->isOnly(GlobalSearchType::Labels)) {
            return;
        }

        $filters = $criteria->activeFilters();

        if (filled($filters['type'] ?? null)) {
            $type = LabelType::tryFrom((string) $filters['type']);

            if ($type instanceof LabelType) {
                $query->where('type', $type->value);
            }
        }

        self::applyLabelSort($query, $criteria->sort());
    }

    public static function applyToPaymentMethodQuery(Builder $query): void
    {
        $criteria = GlobalSearchCriteria::instance();

        if (! $criteria->isOnly(GlobalSearchType::PaymentMethods)) {
            return;
        }

        $filters = $criteria->activeFilters();

        if (filled($filters['is_system'] ?? null)) {
            $query->where('is_system', self::booleanFilterValue($filters['is_system']));
        }

        self::applyPaymentMethodSort($query, $criteria->sort());
    }

    public static function applyToFamilyMemberQuery(Builder $query): void
    {
        $criteria = GlobalSearchCriteria::instance();

        if (! $criteria->isOnly(GlobalSearchType::FamilyMembers)) {
            return;
        }

        $filters = $criteria->activeFilters();

        if (filled($filters['allowlist_enabled'] ?? null)) {
            $query->where('allowlist_enabled', self::booleanFilterValue($filters['allowlist_enabled']));
        }

        if (filled($filters['login_enabled'] ?? null)) {
            $query->where('login_enabled', self::booleanFilterValue($filters['login_enabled']));
        }

        self::applyFamilyMemberSort($query, $criteria->sort());
    }

    public static function applyToBackupQuery(Builder $query): void
    {
        $criteria = GlobalSearchCriteria::instance();

        if (! $criteria->isOnly(GlobalSearchType::Backups)) {
            return;
        }

        $filters = $criteria->activeFilters();

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('updated_at', '>=', Carbon::parse((string) $filters['from'])->toDateString());
        }

        if (filled($filters['until'] ?? null)) {
            $query->whereDate('updated_at', '<=', Carbon::parse((string) $filters['until'])->toDateString());
        }

        self::applyBackupSort($query, $criteria->sort());
    }

    public static function applyExpenseSort(Builder $query, string $sort): void
    {
        $model = $query->getModel();

        match ($sort) {
            'date_desc' => $query->orderByDesc($model->qualifyColumn('date_time')),
            'date_asc' => $query->orderBy($model->qualifyColumn('date_time')),
            'total_desc' => $query->orderByDesc($model->qualifyColumn('total_amount')),
            'total_asc' => $query->orderBy($model->qualifyColumn('total_amount')),
            'merchant_asc' => $query->orderBy($model->qualifyColumn('merchant_name')),
            default => $query->latest($model->getQualifiedUpdatedAtColumn()),
        };
    }

    public static function applyBudgetSort(Builder $query, string $sort): void
    {
        $model = $query->getModel();

        match ($sort) {
            'name_asc' => $query->orderBy($model->qualifyColumn('title')),
            'amount_desc' => $query->orderByDesc($model->qualifyColumn('amount')),
            'amount_asc' => $query->orderBy($model->qualifyColumn('amount')),
            default => $query->latest($model->getQualifiedUpdatedAtColumn()),
        };
    }

    public static function applyRecurringSort(Builder $query, string $sort): void
    {
        $model = $query->getModel();

        match ($sort) {
            'title_asc' => $query->orderBy($model->qualifyColumn('title')),
            default => $query->latest($model->getQualifiedUpdatedAtColumn()),
        };
    }

    public static function applyLabelSort(Builder $query, string $sort): void
    {
        $model = $query->getModel();

        match ($sort) {
            'name_asc' => $query->orderBy($model->qualifyColumn('name')),
            default => $query->latest($model->getQualifiedUpdatedAtColumn()),
        };
    }

    public static function applyPaymentMethodSort(Builder $query, string $sort): void
    {
        $model = $query->getModel();

        match ($sort) {
            'name_asc' => $query->orderBy($model->qualifyColumn('name')),
            default => $query->latest($model->getQualifiedUpdatedAtColumn()),
        };
    }

    public static function applyFamilyMemberSort(Builder $query, string $sort): void
    {
        $model = $query->getModel();

        match ($sort) {
            'name_asc' => $query->orderBy($model->qualifyColumn('name')),
            default => $query->latest($model->getQualifiedUpdatedAtColumn()),
        };
    }

    public static function applyBackupSort(Builder $query, string $sort): void
    {
        $model = $query->getModel();

        match ($sort) {
            'filename_asc' => $query->orderBy($model->qualifyColumn('filename')),
            default => $query->latest($model->getQualifiedUpdatedAtColumn()),
        };
    }

    /**
     * @param  list<GlobalSearchResult>  $results
     * @return list<GlobalSearchResult>
     */
    public static function sortDestinationResults(array $results, string $sort): array
    {
        if (! in_array($sort, ['title_asc', 'title_desc'], true)) {
            return $results;
        }

        usort($results, function ($left, $right) use ($sort): int {
            $comparison = strcasecmp((string) $left->title, (string) $right->title);

            return $sort === 'title_desc' ? -$comparison : $comparison;
        });

        return $results;
    }

    /**
     * @param  array<string, array<int, mixed>>  $categories
     * @return array<string, array<int, mixed>>
     */
    public static function sortAllTypeCategories(array $categories, string $sort): array
    {
        if (! in_array($sort, ['title_asc', 'title_desc'], true)) {
            return $categories;
        }

        foreach ($categories as $name => $results) {
            $normalizedResults = $results instanceof Collection ? $results->all() : $results;

            $categories[$name] = self::sortDestinationResults($normalizedResults, $sort);
        }

        return $categories;
    }

    /**
     * @return array<string, string>
     */
    public static function budgetAssignedToOptions(): array
    {
        $primaryUser = User::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('household_role', HouseholdRole::Primary->value)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first(['name', 'display_name']);

        $primaryLabel = 'Primary';

        if ($primaryUser instanceof User) {
            $primaryLabel = filled($primaryUser->display_name)
                ? (string) $primaryUser->display_name
                : (string) $primaryUser->name;
        }

        return [
            'primary' => $primaryLabel,
            ...FamilyMember::query()
                ->orderBy('name')
                ->get(['id', 'name', 'display_name'])
                ->mapWithKeys(fn (FamilyMember $familyMember): array => [
                    (string) $familyMember->getKey() => filled($familyMember->display_name)
                        ? (string) $familyMember->display_name
                        : (string) $familyMember->name,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function expenseUploadedByOptions(): array
    {
        return FamilyMember::query()
            ->orderBy('name')
            ->get(['id', 'name', 'display_name'])
            ->mapWithKeys(fn (FamilyMember $familyMember): array => [
                $familyMember->getKey() => filled($familyMember->display_name)
                    ? (string) $familyMember->display_name
                    : (string) $familyMember->name,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function expenseLabelOptions(): array
    {
        return Label::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function booleanFilterValue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    /**
     * @return array<int, string>
     */
    public static function expensePaymentMethodOptions(): array
    {
        return PaymentMethod::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
