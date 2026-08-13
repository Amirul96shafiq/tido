<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses;

use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Resources\Expenses\Tables\ExpensesTable;
use App\Helpers\MoneyDisplay;
use App\Models\Expense;
use App\Models\ExpenseItem;
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

    public static function getGlobalSearchResults(string $search): Collection
    {
        static::$globalSearchQuery = $search;

        try {
            return parent::getGlobalSearchResults($search);
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
        $details = [
            'Expense #' => $record->invoice_number ?? '—',
            'Date' => $record->date_time?->format('d M Y') ?? '—',
            'Total' => MoneyDisplay::withCurrency($record->total_amount, $record->displayCurrency()),
            'Status' => $record->status,
        ];

        $conversionSummary = MoneyDisplay::conversionSummary($record);
        if ($conversionSummary !== null) {
            $details['Currency'] = $conversionSummary;
        }

        $matchingItems = static::matchingExpenseItemLabels($record, static::$globalSearchQuery);

        if ($matchingItems !== []) {
            $details['Items'] = implode(', ', $matchingItems);
        }

        return $details;
    }

    /**
     * @return list<string>
     */
    protected static function matchingExpenseItemLabels(Expense $record, ?string $search): array
    {
        if (blank($search) || ! $record->relationLoaded('expenseItems')) {
            return [];
        }

        $terms = static::globalSearchTerms($search);

        if ($terms === []) {
            return [];
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
            ->map(function (ExpenseItem $item): string {
                $description = trim((string) ($item->description ?? ''));

                if ($description !== '') {
                    return $description;
                }

                $serial = trim((string) ($item->serial_number ?? ''));

                if ($serial !== '') {
                    return $serial;
                }

                return trim((string) ($item->label?->name ?? ''));
            })
            ->filter()
            ->unique()
            ->take(3)
            ->values()
            ->all();
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
