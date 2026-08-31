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
    public string $type = 'all';

    public string $sort = 'default';

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $filters = [];

    public function mount(): void
    {
        $this->ensureFilterBuckets();
    }

    public function updated(string $property): void
    {
        if ($property === 'type') {
            $this->sort = GlobalSearchType::tryFromValue($this->type)->defaultSort();
        }
    }

    public function resetFilters(): void
    {
        $searchType = GlobalSearchType::tryFromValue($this->type);
        $this->filters[$searchType->value] = $searchType->defaultFilters();
    }

    public function clearFilters(): void
    {
        $this->resetFilters();
    }

    public function resetModalState(): void
    {
        $this->search = '';
        $this->type = GlobalSearchType::All->value;
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
        return GlobalSearchType::tryFromValue($this->type)->sortOptions();
    }

    public function getActiveFiltersCount(): int
    {
        $searchType = GlobalSearchType::tryFromValue($this->type);

        if (! $searchType->hasTypeFilters()) {
            return 0;
        }

        return collect($this->filters[$searchType->value] ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->count();
    }

    public function filtersForm(Schema $schema): Schema
    {
        $searchType = GlobalSearchType::tryFromValue($this->type);

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
        $searchType = GlobalSearchType::tryFromValue($this->type);
        $this->ensureFilterBuckets();

        GlobalSearchCriteria::apply(
            $searchType,
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
