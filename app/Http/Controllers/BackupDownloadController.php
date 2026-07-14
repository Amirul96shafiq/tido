<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\BackupService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(Backup $backup, BackupService $backupService): StreamedResponse
    {
        return $backupService->downloadResponse($backup);
    }
}
