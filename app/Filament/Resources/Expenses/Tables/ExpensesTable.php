<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses\Tables;

use App\Enums\HouseholdRole;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Support\RecordActionsGroup;
use App\Filament\Tables\Columns\LightweightSelectColumn;
use App\Helpers\FilenameDisplay;
use App\Helpers\MoneyDisplay;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ReceiptReparseService;
use App\Support\HouseholdAccess;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Xplodman\CountUp\Tables\Columns\CountUpColumn;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        $statusOptions = self::statusOptions();
        $uploadedByOptions = self::uploadedByOptions();
        $primaryUsername = self::primaryUsername();

        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                FilenameDisplay::configureTextColumn(
                    TextColumn::make('original_filename')
                        ->label('Filename')
                        ->searchable()
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
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_time')
                    ->label('Buy date')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                CountUpColumn::make('total_amount')
                    ->countUpDecimals(2)
                    ->countUpPrefix(fn (Expense $record): string => MoneyDisplay::prefixForCurrency($record->displayCurrency()).' ')
                    ->tooltip(fn (Expense $record): ?string => MoneyDisplay::conversionSummary($record))
                    ->sortable(),

                CountUpColumn::make('discount_total')
                    ->countUpDecimals(2)
                    ->countUpPrefix(fn (Expense $record): string => MoneyDisplay::prefixForCurrency($record->displayCurrency()).' ')
                    ->tooltip(fn (Expense $record): ?string => MoneyDisplay::conversionSummary($record))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('paymentMethod.name')
                    ->label('Payment Method')
                    ->badge()
                    ->icon(fn ($record): ?string => $record->paymentMethod?->icon)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'info',
                        'whatsapp' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                LightweightSelectColumn::make('family_member_id')
                    ->label('Uploaded By')
                    ->options($uploadedByOptions)
                    ->placeholder($primaryUsername)
                    ->width('9rem')
                    ->disabled(fn (Expense $record): bool => HouseholdAccess::isFamilyMember()
                        || ! HouseholdAccess::canMutateExpense($record))
                    ->toggleable(isToggledHiddenByDefault: false),

                LightweightSelectColumn::make('status')
                    ->options($statusOptions)
                    ->selectablePlaceholder(false)
                    ->width('9rem')
                    ->disabled(fn (Expense $record): bool => ! HouseholdAccess::canMutateExpense($record))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('editedBy.name')
                    ->label('Edited By')
                    ->formatStateUsing(fn (?string $state, Expense $record): ?string => filled($record->editedBy?->display_name)
                        ? (string) $record->editedBy->display_name
                        : $state)
                    ->placeholder('System')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Edited At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options($statusOptions)
                    ->searchable(),

                SelectFilter::make('source')
                    ->options([
                        'manual' => 'Manual',
                        'whatsapp' => 'WhatsApp',
                    ])
                    ->searchable(),

                SelectFilter::make('family_member_id')
                    ->label('Uploaded By')
                    ->options($uploadedByOptions)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('payment_method_id')
                    ->label('Payment Method')
                    ->relationship('paymentMethod', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('date_time')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, mixed $date): Builder => $query->whereDate('date_time', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, mixed $date): Builder => $query->whereDate('date_time', '<=', $date),
                            );
                    }),

                TrashedFilter::make()
                    ->searchable(),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (Expense $record): bool => HouseholdAccess::canMutateExpense($record),
            )
            ->recordClasses(fn (Expense $record): array => HouseholdAccess::canMutateExpense($record)
                ? []
                : ['fi-ta-record-with-content-prefix', 'tido-ta-record-unsupported'])
            ->recordUrl(fn (Expense $record): ?string => ExpenseResource::canEdit($record)
                ? ExpenseResource::getUrl('edit', ['record' => $record])
                : null)
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                RecordActionsGroup::make([
                    EditAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Expense $record): string => self::familyMemberActionAuthorizationMessage($record)),
                    Action::make('reparse')
                        ->label('Reparse')
                        ->icon(Heroicon::ArrowPath)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reparse receipt')
                        ->modalDescription('Clear line items, reset status to pending, and queue OCR again.')
                        ->authorize(fn (Expense $record): bool => ExpenseResource::canEdit($record))
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Expense $record): string => self::familyMemberActionAuthorizationMessage($record))
                        ->visible(fn (Expense $record): bool => filled($record->image_path)
                            && Storage::exists((string) $record->image_path))
                        ->action(function (Expense $record, ReceiptReparseService $reparseService): void {
                            $reparseService->reparse($record);

                            Notification::make()
                                ->title('Reparse queued')
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Expense $record): string => self::familyMemberActionAuthorizationMessage($record)),
                    RestoreAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Expense $record): string => self::familyMemberActionAuthorizationMessage($record)),
                    ForceDeleteAction::make()
                        ->authorizationTooltip()
                        ->authorizationMessage(fn (Expense $record): string => self::familyMemberActionAuthorizationMessage($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                    ForceDeleteBulkAction::make()
                        ->authorizeIndividualRecords('forceDelete'),
                    RestoreBulkAction::make()
                        ->authorizeIndividualRecords('restore'),
                ]),
            ])
            ->emptyStateHeading('No expenses yet')
            ->emptyStateDescription('Upload a receipt or add an expense to start tracking spending.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Action::make('uploadReceipts')
                    ->label('Add Receipts')
                    ->icon(Heroicon::Plus)
                    ->url(ReceiptUploadPage::getUrl())
                    ->button(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pending Parsing',
            'parsed' => 'Parsed by AI',
            'reviewed' => 'Reviewed',
            'requires_manual_review' => 'Requires Manual Review',
            'failed' => 'Parsing Failed',
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public static function uploadedByOptions(): array
    {
        return once(static function (): array {
            return FamilyMember::query()
                ->orderBy('name')
                ->get(['id', 'name', 'display_name'])
                ->mapWithKeys(fn (FamilyMember $familyMember): array => [
                    $familyMember->getKey() => filled($familyMember->display_name)
                        ? (string) $familyMember->display_name
                        : (string) $familyMember->name,
                ])
                ->all();
        });
    }

    public static function uploadedByLabel(?int $familyMemberId): string
    {
        if ($familyMemberId === null) {
            return self::primaryUsername();
        }

        $options = self::uploadedByOptions();

        return $options[$familyMemberId] ?? (string) $familyMemberId;
    }

    public static function primaryUsername(): string
    {
        return once(static function (): string {
            $primaryUser = User::query()
                ->where(function (Builder $query): void {
                    $query
                        ->where('household_role', HouseholdRole::Primary->value)
                        ->orWhereNull('household_role');
                })
                ->orderBy('id')
                ->first(['name', 'display_name']);

            if (! $primaryUser instanceof User) {
                return 'Primary username';
            }

            return filled($primaryUser->display_name)
                ? (string) $primaryUser->display_name
                : (string) $primaryUser->name;
        });
    }

    public static function familyMemberActionAuthorizationMessage(Expense $record): string
    {
        $familyMember = $record->familyMember;

        $username = $familyMember === null
            ? self::primaryUsername()
            : (filled($familyMember->display_name)
                ? (string) $familyMember->display_name
                : (string) $familyMember->name);

        return "Only {$username} able to use this CTA button.";
    }
}
