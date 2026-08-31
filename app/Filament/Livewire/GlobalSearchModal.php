<?php

declare(strict_types=1);

namespace App\Filament\Livewire;

use App\Enums\LabelType;
use App\Enums\RecurringType;
use App\Filament\GlobalSearch\AppliesGlobalSearchCriteria;
use App\Filament\GlobalSearch\GlobalSearchCriteria;
use App\Filament\GlobalSearch\GlobalSearchType;
use App\Filament\GlobalSearch\TidoSearchEngine;
use CharrafiMed\GlobalSearchModal\Livewire\GlobalSearchModal as BaseGlobalSearchModal;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;

class GlobalSearchModal extends BaseGlobalSearchModal
{
    /**
     * @var list<string>
     */
    public array $type = ['all'];

    public string $sort = 'default';

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $filters = [];

    public function mount(): void
    {
        $this->type = $this->normalizedTypeValues();
        $this->ensureFilterBuckets();
    }

    public function updated(string $property): void
    {
        if ($property !== 'type' && ! str_starts_with($property, 'type.')) {
            return;
        }

        $normalized = $this->normalizedTypeValues();

        if ($this->type !== $normalized) {
            $this->type = $normalized;
        }

        $this->sort = $this->sortType()->defaultSort();
    }

    public function toggleType(string $value): void
    {
        $allowed = array_keys(GlobalSearchType::optionsForUser());

        if (! in_array($value, $allowed, true)) {
            return;
        }

        if ($value === GlobalSearchType::All->value) {
            $this->type = [GlobalSearchType::All->value];
            $this->sort = $this->sortType()->defaultSort();

            return;
        }

        $selected = array_values(array_filter(
            $this->normalizedTypeValues(),
            fn (string $type): bool => $type !== GlobalSearchType::All->value,
        ));

        if (in_array($value, $selected, true)) {
            $selected = array_values(array_filter(
                $selected,
                fn (string $type): bool => $type !== $value,
            ));
        } else {
            $selected[] = $value;
        }

        $this->type = $selected === []
            ? [GlobalSearchType::All->value]
            : array_values(array_intersect($allowed, $selected));
        $this->sort = $this->sortType()->defaultSort();
    }

    public function resetFilters(): void
    {
        foreach ($this->selectedTypes() as $searchType) {
            if ($searchType->defaultFilters() === []) {
                continue;
            }

            $this->filters[$searchType->value] = $searchType->defaultFilters();
        }
    }

    public function clearFilters(): void
    {
        $this->resetFilters();
    }

    public function resetModalState(): void
    {
        $this->search = '';
        $this->type = [GlobalSearchType::All->value];
        $this->sort = GlobalSearchType::All->defaultSort();
        $this->filters = [];
        $this->ensureFilterBuckets();
        GlobalSearchCriteria::reset();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return GlobalSearchType::optionsForUser();
    }

    #[Computed]
    public function sortOptions(): array
    {
        return $this->sortType()->sortOptions();
    }

    /**
     * @return list<string>
     */
    public function normalizedTypeValues(): array
    {
        $allowed = array_keys(GlobalSearchType::optionsForUser());
        $values = array_values(array_intersect(
            $allowed,
            array_map(
                fn (GlobalSearchType $type): string => $type->value,
                GlobalSearchType::tryFromValues($this->type),
            ),
        ));

        return $values === [] ? [GlobalSearchType::All->value] : $values;
    }

    /**
     * @return list<GlobalSearchType>
     */
    public function selectedTypes(): array
    {
        return array_map(
            fn (string $value): GlobalSearchType => GlobalSearchType::tryFromValue($value),
            $this->normalizedTypeValues(),
        );
    }

    public function sortType(): GlobalSearchType
    {
        $types = $this->selectedTypes();

        return count($types) === 1 ? $types[0] : GlobalSearchType::All;
    }

    public function isAllTypes(): bool
    {
        return $this->normalizedTypeValues() === [GlobalSearchType::All->value];
    }

    public function isTypeSelected(string $value): bool
    {
        if ($value === GlobalSearchType::All->value) {
            return $this->isAllTypes();
        }

        return in_array($value, $this->normalizedTypeValues(), true);
    }

    public function hasTypeFilters(): bool
    {
        $types = $this->selectedTypes();

        return count($types) === 1 && $types[0]->hasTypeFilters();
    }

    public function filtersNeedSingleType(): bool
    {
        return $this->isAllTypes() || count($this->selectedTypes()) > 1;
    }

    public function typeTooltipLabel(): string
    {
        return collect($this->selectedTypes())
            ->map(fn (GlobalSearchType $type): string => $type->label())
            ->implode(', ');
    }

    public function filtersTooltipLabel(): string
    {
        if ($this->hasTypeFilters()) {
            return $this->sortType()->label().' Filters';
        }

        if ($this->filtersNeedSingleType()) {
            return 'Select one type to use filters';
        }

        return 'Filters are not available for this type';
    }

    public function getActiveFiltersCount(): int
    {
        if (! $this->hasTypeFilters()) {
            return 0;
        }

        $searchType = $this->sortType();

        return collect($this->filters[$searchType->value] ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->count();
    }

    public function filtersForm(Schema $schema): Schema
    {
        $searchType = $this->sortType();

        return $schema
            ->statePath('filters.'.$searchType->value)
            ->live()
            ->components(match ($searchType) {
                GlobalSearchType::Expenses => [
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending' => 'Pending Parsing',
                            'parsed' => 'Parsed by AI',
                            'reviewed' => 'Reviewed',
                            'requires_manual_review' => 'Requires Manual Review',
                            'failed' => 'Parsing Failed',
                        ])
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('source')
                        ->label('Source')
                        ->options([
                            'manual' => 'Manual',
                            'whatsapp' => 'WhatsApp',
                        ])
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('family_member_id')
                        ->label('Uploaded By')
                        ->options(fn (): array => AppliesGlobalSearchCriteria::expenseUploadedByOptions())
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('payment_method_id')
                        ->label('Payment Method')
                        ->options(fn (): array => AppliesGlobalSearchCriteria::expensePaymentMethodOptions())
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('label_id')
                        ->label('Label')
                        ->options(fn (): array => AppliesGlobalSearchCriteria::expenseLabelOptions())
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                    DatePicker::make('from')
                        ->label('Buy Date From'),
                    DatePicker::make('until')
                        ->label('Buy Date Until'),
                    TextInput::make('total_min')
                        ->label('Total Min')
                        ->myr()
                        ->placeholder('0.00'),
                    TextInput::make('total_max')
                        ->label('Total Max')
                        ->myr()
                        ->placeholder('0.00'),
                ],
                GlobalSearchType::Budgets => [
                    Select::make('period')
                        ->label('Period')
                        ->options([
                            'daily' => 'Daily',
                            'weekly' => 'Weekly',
                            'monthly' => 'Monthly',
                            'quarterly' => 'Quarterly',
                            'yearly' => 'Yearly',
                        ])
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('family_member_id')
                        ->label('Assigned To')
                        ->options(fn (): array => AppliesGlobalSearchCriteria::budgetAssignedToOptions())
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('is_shared')
                        ->label('Shared')
                        ->options([
                            '1' => 'Shared',
                            '0' => 'Personal',
                        ])
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('is_active')
                        ->label('Active')
                        ->options([
                            '1' => 'Active',
                            '0' => 'Inactive',
                        ])
                        ->native(false)
                        ->placeholder('All'),
                ],
                GlobalSearchType::Recurrings => [
                    Select::make('type')
                        ->label('Type')
                        ->options(RecurringType::options())
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('is_active')
                        ->label('Active')
                        ->options([
                            '1' => 'Active',
                            '0' => 'Inactive',
                        ])
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('is_shared')
                        ->label('Shared')
                        ->options([
                            '1' => 'Shared',
                            '0' => 'Personal',
                        ])
                        ->native(false)
                        ->placeholder('All'),
                ],
                GlobalSearchType::Labels => [
                    Select::make('type')
                        ->label('Type')
                        ->options(LabelType::options())
                        ->searchable()
                        ->native(false)
                        ->placeholder('All'),
                ],
                GlobalSearchType::PaymentMethods => [
                    Select::make('is_system')
                        ->label('System Lock')
                        ->options([
                            '1' => 'Yes',
                            '0' => 'No',
                        ])
                        ->native(false)
                        ->placeholder('All'),
                ],
                GlobalSearchType::FamilyMembers => [
                    Select::make('allowlist_enabled')
                        ->label('Contact Allowlist')
                        ->options([
                            '1' => 'Enabled',
                            '0' => 'Disabled',
                        ])
                        ->native(false)
                        ->placeholder('All'),
                    Select::make('login_enabled')
                        ->label('Panel Login')
                        ->options([
                            '1' => 'Enabled',
                            '0' => 'Disabled',
                        ])
                        ->native(false)
                        ->placeholder('All'),
                ],
                GlobalSearchType::Backups => [
                    DatePicker::make('from')
                        ->label('From'),
                    DatePicker::make('until')
                        ->label('Until'),
                ],
                default => [],
            });
    }

    public function getResults(): ?GlobalSearchResults
    {
        $this->ensureFilterBuckets();

        GlobalSearchCriteria::apply(
            $this->selectedTypes(),
            $this->sort,
            $this->filters,
        );

        return app(TidoSearchEngine::class)->search((string) $this->search);
    }

    public function render(): View
    {
        return view('global-search-modal::components.global-search-modal', [
            'results' => $this->getResults(),
        ]);
    }

    protected function ensureFilterBuckets(): void
    {
        foreach (GlobalSearchType::cases() as $searchType) {
            if ($searchType->defaultFilters() === []) {
                continue;
            }

            $this->filters[$searchType->value] ??= $searchType->defaultFilters();
        }
    }
}
