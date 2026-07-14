<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GuestRestoreBackupRequest;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class GuestRestoreBackupController extends Controller
{
    public function __invoke(GuestRestoreBackupRequest $request, BackupService $backupService): JsonResponse
    {
        if (User::query()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Restore is unavailable.',
            ], 403);
        }

        $plainToken = (string) $request->validated('token');
        $uploadedFile = $request->file('backup');

        if ($uploadedFile === null) {
            return response()->json([
                'success' => false,
                'message' => 'Choose a backup zip file to restore.',
            ], 422);
        }

        $backup = $backupService->findBackupByRestoreToken($plainToken);

        if ($backup === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid restore token or backup.',
            ], 422);
        }

        $tempDirectory = storage_path('app/backup-restore/'.uniqid('guest_', true));
        File::ensureDirectoryExists($tempDirectory);

        $zipPath = $tempDirectory.'/'.$uploadedFile->getClientOriginalName();

        try {
            $uploadedFile->move($tempDirectory, $uploadedFile->getClientOriginalName());

            $backupService->restoreFromZipPath($zipPath);
            $backupService->consumeRestoreToken($backup);

            return response()->json([
                'success' => true,
                'message' => 'Backup restored. Please sign in.',
                'redirect' => url('/admin/login'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $message = $exception instanceof RuntimeException
                ? 'Invalid restore token or backup.'
                : 'Restore failed. Try again.';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        } finally {
            if (File::isDirectory($tempDirectory)) {
                File::deleteDirectory($tempDirectory);
            }
        }
    }
}
