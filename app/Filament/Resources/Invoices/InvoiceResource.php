<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $recordTitleAttribute = 'merchant_name';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 1;

    protected static int $globalSearchResultsLimit = 20;

    protected static ?string $globalSearchQuery = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
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
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'merchant_name',
            'invoice_number',
            'notes',
            'original_filename',
            'invoiceItems.description',
            'invoiceItems.serial_number',
            'invoiceItems.label.name',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['invoiceItems.label']);
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
        /** @var Invoice $record */
        $details = [
            'Invoice #' => $record->invoice_number ?? '—',
            'Date' => $record->date_time?->format('d M Y') ?? '—',
            'Total' => 'RM '.number_format((float) $record->total_amount, 2),
            'Status' => $record->status,
        ];

        $matchingItems = static::matchingInvoiceItemLabels($record, static::$globalSearchQuery);

        if ($matchingItems !== []) {
            $details['Items'] = implode(', ', $matchingItems);
        }

        return $details;
    }

    /**
     * @return list<string>
     */
    protected static function matchingInvoiceItemLabels(Invoice $record, ?string $search): array
    {
        if (blank($search) || ! $record->relationLoaded('invoiceItems')) {
            return [];
        }

        $terms = static::globalSearchTerms($search);

        if ($terms === []) {
            return [];
        }

        return $record->invoiceItems
            ->filter(function (InvoiceItem $item) use ($terms): bool {
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
            ->map(function (InvoiceItem $item): string {
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
