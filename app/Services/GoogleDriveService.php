<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleDriveService
{
    public function sync(): int
    {
        $count = 0;

        try {
            $disk = Storage::disk('google');
            $files = $disk->files('/');

            foreach ($files as $file) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (! in_array($extension, ['jpg', 'jpeg', 'png'])) {
                    continue;
                }

                $filename = basename($file);
                $localPath = 'receipts/' . uniqid() . '_' . $filename;

                $contents = $disk->get($file);
                Storage::put($localPath, $contents);

                Invoice::create([
                    'merchant_name' => 'Pending AI Extraction...',
                    'date_time' => now(),
                    'subtotal' => 0.00,
                    'total_tax' => 0.00,
                    'total_amount' => 0.00,
                    'currency' => 'MYR',
                    'source' => 'google_drive',
                    'status' => 'pending',
                    'image_path' => $localPath,
                    'original_filename' => $filename,
                    'google_drive_file_id' => $file,
                ]);

                $disk->delete($file);
                $count++;
            }
        } catch (\Throwable $e) {
            Log::error('Google Drive Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $count;
    }
}
