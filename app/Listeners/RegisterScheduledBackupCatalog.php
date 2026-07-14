<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\BackupService;
use Spatie\Backup\Events\BackupWasSuccessful;

class RegisterScheduledBackupCatalog
{
    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    public function handle(BackupWasSuccessful $event): void
    {
        if (! BackupService::shouldRegisterScheduledCatalog()) {
            return;
        }

        $this->backupService->registerFromScheduledBackup($event->backupDestination);
    }
}
