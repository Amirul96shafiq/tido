<?php

declare(strict_types=1);

namespace App\Filament\Resources\Backups\Tables;

use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use App\Support\FilamentAuthLogout;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BackupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (BackupType $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('filename')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (Backup $record): string => $record->formattedSize())
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->placeholder('System')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, mixed $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, mixed $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (Backup $record, BackupService $backupService) => $backupService->downloadResponse($record)),
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restore backup')
                    ->modalDescription('This will replace all current database data with this backup. You will be signed out after restore completes.')
                    ->modalSubmitActionLabel('Restore backup')
                    ->action(function (Backup $record, BackupService $backupService, BackupNotificationService $backupNotificationService) {
                        $user = auth()->user();

                        if ($user instanceof User) {
                            $backupNotificationService->notifyRestored($user, $record);
                        }

                        $backupService->restore($record);

                        Notification::make()
                            ->title('Backup restored')
                            ->body('Database restored successfully. Please sign in again.')
                            ->success()
                            ->send();

                        FilamentAuthLogout::logoutToLogin();

                        return redirect()->to(Filament::getLoginUrl());
                    }),
                DeleteAction::make()
                    ->modalHeading('Delete backup')
                    ->modalDescription('This removes the backup file and catalog entry. It cannot be undone.')
                    ->successNotificationTitle('Backup deleted')
                    ->action(function (Backup $record, BackupService $backupService, BackupNotificationService $backupNotificationService): void {
                        $user = auth()->user();
                        $filename = $record->filename;

                        if ($user instanceof User) {
                            $backupNotificationService->notifyDeleted($user, $filename);
                        }

                        $backupService->delete($record);
                    }),
            ])
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('Create a backup to save a restore point.')
            ->emptyStateIcon('heroicon-o-circle-stack')
            ->emptyStateActions([
                Action::make('createBackup')
                    ->label('Create backup')
                    ->icon(Heroicon::Plus)
                    ->button()
                    ->action(function (BackupService $backupService, BackupNotificationService $backupNotificationService): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        $backup = $backupService->create(
                            BackupType::Manual,
                            $user,
                        );

                        $backupNotificationService->notifyCreated($user, $backup);

                        Notification::make()
                            ->title('Backup created')
                            ->body('A new database backup has been saved.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
