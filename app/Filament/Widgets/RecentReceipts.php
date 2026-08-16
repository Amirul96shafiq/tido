<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesTableOnExpenseBroadcast;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Tables\ExpensesTable;
use App\Filament\Widgets\Concerns\HasDashboardSectionId;
use App\Filament\Widgets\Concerns\InteractsWithDashboardMonth;
use App\Helpers\FilenameDisplay;
use App\Helpers\MoneyDisplay;
use App\Models\Expense;
use App\Support\DashboardSpenderScope;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentReceipts extends BaseWidget
{
    use HasDashboardSectionId;
    use InteractsWithDashboardMonth;
    use RefreshesTableOnExpenseBroadcast;

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-receipts';

    public static function dashboardSectionId(): string
    {
        return 'recent-receipts';
    }

    public function table(Table $table): Table
    {
        $bounds = $this->getSelectedMonthBounds();
        $spenderScope = DashboardSpenderScope::fromFilters($this->pageFilters ?? []);

        $query = Expense::query()
            ->inPeriod($bounds['start'], $bounds['end']);
        $spenderScope->applyToExpenseQuery($query);

        return $table
            ->heading('Recent Receipts ('.$this->formatSelectedMonth('F Y').')')
            ->query($query->limit(5))
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->headerActions([
                Action::make('viewRecentUploads')
                    ->label('View all')
                    ->icon(Heroicon::ArrowRight)
                    ->color('primary')
                    ->url(ReceiptUploadPage::getUrl().'#recent-uploads')
                    ->button(),
            ])
            ->columns([
                FilenameDisplay::configureTextColumn(
                    TextColumn::make('original_filename')
                        ->label('Filename')
                        ->sortable()
                        ->weight(FontWeight::Medium)
                        ->color(fn (Expense $record): ?string => filled($record->image_path) ? 'primary' : null)
                        ->tooltip(fn (Expense $record): ?string => filled($record->image_path) ? (string) $record->original_filename : null)
                        ->url(
                            fn (Expense $record): ?string => $record->fileUrl(),
                            shouldOpenInNewTab: true,
                        ),
                ),

                TextColumn::make('merchant_name')
                    ->label('Merchant Name')
                    ->sortable()
                    ->limit(20)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->formatStateUsing(fn (?string $state, Expense $record): string => MoneyDisplay::withCurrency(
                        $state,
                        $record->displayCurrency(),
                    ))
                    ->tooltip(fn (Expense $record): ?string => MoneyDisplay::conversionSummary($record))
                    ->sortable(),

                TextColumn::make('paymentMethod.name')
                    ->label('Payment Method')
                    ->badge()
                    ->icon(fn (Expense $record): ?string => $record->paymentMethod?->icon)
                    ->placeholder('-'),

                TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'info',
                        'whatsapp' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'parsed' => 'info',
                        'reviewed' => 'success',
                        'requires_manual_review' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->extraCellAttributes(fn (Expense $record): array => $record->status === 'pending'
                        ? ['class' => 'tido-expense-status-pending']
                        : [])
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (Expense $record): bool => ExpenseResource::canEdit($record))
                    ->authorizationTooltip()
                    ->authorizationMessage(fn (Expense $record): string => ExpensesTable::familyMemberActionAuthorizationMessage($record))
                    ->url(
                        fn (Expense $record): string => ExpenseResource::getUrl('edit', ['record' => $record]),
                    ),
            ])
            ->emptyStateHeading('No receipts')
            ->emptyStateDescription('No receipts recorded for this month.')
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->emptyStateActions([
                Action::make('uploadReceipts')
                    ->label('Upload Receipts')
                    ->icon(Heroicon::Plus)
                    ->url(ReceiptUploadPage::getUrl())
                    ->button(),
            ]);
    }
}
