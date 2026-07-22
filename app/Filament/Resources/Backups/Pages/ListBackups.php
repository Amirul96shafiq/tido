<?php

declare(strict_types=1);

namespace App\Filament\Resources\Backups\Pages;

use App\Enums\BackupType;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Resources\Backups\BackupResource;
use App\Models\User;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBackups extends ListRecords
{
    use PrependsHomeBreadcrumb;

    protected static string $resource = BackupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBackup')
                ->label('Create backup')
                ->icon(Heroicon::Plus)
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
        ];
    }
}
