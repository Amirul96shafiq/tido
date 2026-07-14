<?php

declare(strict_types=1);

namespace App\Services;

use App\Filament\Resources\Backups\BackupResource;
use App\Models\Backup;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class BackupNotificationService
{
    public function notifyCreated(User $user, Backup $backup): void
    {
        Notification::make()
            ->title('Backup created')
            ->body("Database backup {$backup->filename} was saved successfully.")
            ->success()
            ->icon('heroicon-o-circle-stack')
            ->actions([
                Action::make('viewBackups')
                    ->label('View backups')
                    ->button()
                    ->url(BackupResource::getUrl('index'), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    public function notifyRestored(User $user, Backup $backup): void
    {
        Notification::make()
            ->title('Backup restored')
            ->body("Database was restored from {$backup->filename}. Please sign in again.")
            ->success()
            ->icon('heroicon-o-arrow-path')
            ->sendToDatabase($user);
    }

    public function notifyDeleted(User $user, string $filename): void
    {
        Notification::make()
            ->title('Backup deleted')
            ->body("Backup {$filename} was removed.")
            ->success()
            ->icon('heroicon-o-trash')
            ->actions([
                Action::make('viewBackups')
                    ->label('View backups')
                    ->button()
                    ->url(BackupResource::getUrl('index'), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }
}
