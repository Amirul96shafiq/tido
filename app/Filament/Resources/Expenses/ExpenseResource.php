<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses;

use App\Filament\GlobalSearch\AppliesGlobalSearchCriteria;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Resources\Expenses\Tables\ExpensesTable;
use App\Helpers\MoneyDisplay;
use App\Models\Expense;
use App\Models\ExpenseItem;
use Filament\Actions\Action;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $recordTitleAttribute = 'merchant_name';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 1;

    protected static int $globalSearchResultsLimit = 20;

    protected static ?string $globalSearchQuery = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['paymentMethod', 'familyMember', 'editedBy']);
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'merchant_name',
            'invoice_number',
            'status',
            'notes',
            'original_filename',
            'expenseItems.description',
            'expenseItems.serial_number',
            'expenseItems.label.name',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['expenseItems.label']);
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        AppliesGlobalSearchCriteria::applyToExpenseQuery($query);
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        static::$globalSearchQuery = $search;

        try {
            $query = static::getGlobalSearchEloquentQuery();

            static::applyGlobalSearchAttributeConstraints($query, $search);
            static::modifyGlobalSearchQuery($query, $search);

            return $query
                ->limit(static::getGlobalSearchResultsLimit())
                ->get()
                ->flatMap(function (Model $record): Collection {
                    /** @var Expense $record */
                    $matchingItems = static::matchingExpenseItems($record, static::$globalSearchQuery);

                    if ($matchingItems->isEmpty()) {
                        $result = static::makeGlobalSearchResult($record);

                        return $result !== null ? collect([$result]) : collect();
                    }

                    return $matchingItems
                        ->map(fn (ExpenseItem $item): ?GlobalSearchResult => static::makeGlobalSearchResult($record, $item))
                        ->filter()
                        ->values();
                })
                ->take(static::getGlobalSearchResultsLimit())
                ->values();
        } finally {
            static::$globalSearchQuery = null;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Expense $record */
        return static::buildGlobalSearchResultDetails($record);
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        /** @var Expense $record */
        return static::buildGlobalSearchResultUrl($record);
    }

    protected static function makeGlobalSearchResult(Expense $record, ?ExpenseItem $item = null): ?GlobalSearchResult
    {
        $url = static::buildGlobalSearchResultUrl($record, $item);

        if (blank($url)) {
            return null;
        }

        return new GlobalSearchResult(
            title: static::getGlobalSearchResultTitle($record),
            url: $url,
            details: static::buildGlobalSearchResultDetails($record, $item),
            actions: array_map(
                fn (Action $action) => $action->hasRecord() ? $action : $action->record($record),
                static::getGlobalSearchResultActions($record),
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    protected static function buildGlobalSearchResultDetails(Expense $record, ?ExpenseItem $item = null): array
    {
        $details = [
            'Expense #' => $record->invoice_number ?? '—',
            'Buy Date' => $record->date_time?->format('d M Y') ?? '—',
            'Last Updated' => $record->updated_at === null
                ? '—'
                : sprintf(
                    '%s (%s)',
                    $record->updated_at->diffForHumans(),
                    $record->updated_at->format('d M Y H:i'),
                ),
            'Total' => MoneyDisplay::withCurrency($record->total_amount, $record->displayCurrency()),
            'Status' => $record->status,
        ];

        $conversionSummary = MoneyDisplay::conversionSummary($record);
        if ($conversionSummary !== null) {
            $details['Currency'] = $conversionSummary;
        }

        if ($item !== null) {
            $details['Item'] = static::expenseItemSearchLabel($item, $record);
        }

        return $details;
    }

    protected static function buildGlobalSearchResultUrl(Expense $record, ?ExpenseItem $item = null): ?string
    {
        if (static::canEdit($record)) {
            $url = static::getUrl('edit', ['record' => $record]);

            if ($item !== null) {
                $url .= '#'.static::expenseItemAnchorId($item);
            }

            return $url;
        }

        return static::getUrl('index', [
            'tableAction' => 'view',
            'tableActionRecord' => $record->getRouteKey(),
        ]);
    }

    public static function expenseItemAnchorId(ExpenseItem $item): string
    {
        return 'expense-item-'.$item->getKey();
    }

    /**
     * @return Collection<int, ExpenseItem>
     */
    protected static function matchingExpenseItems(Expense $record, ?string $search): Collection
    {
        if (blank($search) || ! $record->relationLoaded('expenseItems')) {
            return collect();
        }

        $terms = static::globalSearchTerms($search);

        if ($terms === []) {
            return collect();
        }

        return $record->expenseItems
            ->filter(function (ExpenseItem $item) use ($terms): bool {
                $haystacks = [
                    mb_strtolower((string) ($item->description ?? '')),
                    mb_strtolower((string) ($item->serial_number ?? '')),
                    mb_strtolower((string) ($item->label?->name ?? '')),
                ];

                foreach ($terms as $term) {
                    foreach ($haystacks as $haystack) {
                        if ($haystack !== '' && str_contains($haystack, $term)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @return list<string>
     */
    protected static function matchingExpenseItemLabels(Expense $record, ?string $search): array
    {
        return static::matchingExpenseItems($record, $search)
            ->map(fn (ExpenseItem $item): string => static::expenseItemSearchLabel($item, $record))
            ->filter()
            ->unique()
            ->take(3)
            ->values()
            ->all();
    }

    protected static function expenseItemSearchLabel(ExpenseItem $item, Expense $expense): string
    {
        $label = trim((string) ($item->description ?? ''));

        if ($label === '') {
            $label = trim((string) ($item->serial_number ?? ''));
        }

        if ($label === '') {
            $label = trim((string) ($item->label?->name ?? ''));
        }

        if ($label === '' || $item->line_total === null || $item->line_total === '') {
            return $label;
        }

        return sprintf(
            '%s (%s)',
            $label,
            MoneyDisplay::withCurrency($item->line_total, $expense->displayCurrency()),
        );
    }

    /**
     * @return list<string>
     */
    protected static function globalSearchTerms(string $search): array
    {
        $normalized = mb_strtolower(trim($search));

        if ($normalized === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $normalized) ?: [];

        return array_values(array_unique(array_filter(
            array_merge([$normalized], $words),
            fn (string $term): bool => $term !== '',
        )));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
