<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Recurrings\RecurringResource;
use Filament\Resources\Resource;

enum GlobalSearchType: string
{
    case All = 'all';
    case Pages = 'pages';
    case Sections = 'sections';
    case Expenses = 'expenses';
    case Budgets = 'budgets';
    case Recurrings = 'recurrings';
    case Labels = 'labels';
    case PaymentMethods = 'payment_methods';
    case FamilyMembers = 'family_members';
    case Backups = 'backups';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Pages => 'Pages',
            self::Sections => 'Sections',
            self::Expenses => 'Expenses',
            self::Budgets => 'Budgets',
            self::Recurrings => 'Recurrings',
            self::Labels => 'Labels',
            self::PaymentMethods => 'Payment Methods',
            self::FamilyMembers => 'Family Members',
            self::Backups => 'Backups',
        };
    }

    /**
     * @return class-string<resource>|null
     */
    public function resourceClass(): ?string
    {
        return match ($this) {
            self::Expenses => ExpenseResource::class,
            self::Budgets => BudgetResource::class,
            self::Recurrings => RecurringResource::class,
            self::Labels => LabelResource::class,
            self::PaymentMethods => PaymentMethodResource::class,
            self::FamilyMembers => FamilyMemberResource::class,
            self::Backups => BackupResource::class,
            default => null,
        };
    }

    public function isDestinationType(): bool
    {
        return in_array($this, [self::All, self::Pages, self::Sections], true);
    }

    public function includesPages(): bool
    {
        return in_array($this, [self::All, self::Pages], true);
    }

    public function includesSections(): bool
    {
        return in_array($this, [self::All, self::Sections], true);
    }

    public function includesResource(string $resourceClass): bool
    {
        if ($this === self::All) {
            return true;
        }

        return $this->resourceClass() === $resourceClass;
    }

    public function hasTypeFilters(): bool
    {
        return $this->resourceClass() !== null;
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForUser(): array
    {
        $options = [
            self::All->value => self::All->label(),
            self::Pages->value => self::Pages->label(),
            self::Sections->value => self::Sections->label(),
        ];

        foreach (self::resourceTypes() as $type) {
            $resourceClass = $type->resourceClass();

            if ($resourceClass === null || ! $resourceClass::canGloballySearch()) {
                continue;
            }

            $options[$type->value] = $type->label();
        }

        return $options;
    }

    /**
     * @return list<self>
     */
    public static function resourceTypes(): array
    {
        return [
            self::Expenses,
            self::Budgets,
            self::Recurrings,
            self::Labels,
            self::PaymentMethods,
            self::FamilyMembers,
            self::Backups,
        ];
    }

    public static function tryFromValue(?string $value): self
    {
        if (blank($value)) {
            return self::All;
        }

        return self::tryFrom($value) ?? self::All;
    }

    /**
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        return match ($this) {
            self::All => [
                'default' => 'Default',
                'title_asc' => 'Title A–Z',
                'title_desc' => 'Title Z–A',
            ],
            self::Pages, self::Sections => [
                'relevance' => 'Relevance',
                'title_asc' => 'Title A–Z',
                'title_desc' => 'Title Z–A',
            ],
            self::Expenses => [
                'updated_desc' => 'Last Updated',
                'date_desc' => 'Buy Date Newest',
                'date_asc' => 'Buy Date Oldest',
                'total_desc' => 'Total High–Low',
                'total_asc' => 'Total Low–High',
                'merchant_asc' => 'Merchant A–Z',
            ],
            self::Budgets => [
                'updated_desc' => 'Last Updated',
                'name_asc' => 'Name A–Z',
                'amount_desc' => 'Amount High–Low',
                'amount_asc' => 'Amount Low–High',
            ],
            self::Recurrings => [
                'updated_desc' => 'Last Updated',
                'title_asc' => 'Title A–Z',
            ],
            self::Labels, self::PaymentMethods, self::FamilyMembers => [
                'updated_desc' => 'Last Updated',
                'name_asc' => 'Name A–Z',
            ],
            self::Backups => [
                'updated_desc' => 'Last Updated',
                'filename_asc' => 'Filename A–Z',
            ],
        };
    }

    public function defaultSort(): string
    {
        return array_key_first($this->sortOptions());
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultFilters(): array
    {
        return match ($this) {
            self::Expenses => [
                'status' => null,
                'source' => null,
                'family_member_id' => null,
                'payment_method_id' => null,
                'label_id' => null,
                'from' => null,
                'until' => null,
                'total_min' => null,
                'total_max' => null,
            ],
            self::Budgets => [
                'period' => null,
                'family_member_id' => null,
                'is_shared' => null,
                'is_active' => null,
            ],
            self::Recurrings => [
                'type' => null,
                'is_active' => null,
                'is_shared' => null,
            ],
            self::Labels => [
                'type' => null,
            ],
            self::PaymentMethods => [
                'is_system' => null,
            ],
            self::FamilyMembers => [
                'allowlist_enabled' => null,
                'login_enabled' => null,
            ],
            self::Backups => [
                'from' => null,
                'until' => null,
            ],
            default => [],
        };
    }
}
