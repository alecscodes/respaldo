<?php

namespace App\Services;

use App\Models\App;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupService
{
    public function __construct(
        protected DiskSpaceService $diskSpaceService,
        protected TelegramNotificationService $telegramService
    ) {}

    public function createBackup(App $app, UploadedFile $file, int $userId): string
    {
        // Check disk space before attempting backup
        $diskSpace = $this->diskSpaceService->getBackupDiskSpace();

        // Warn if disk space is above 90%
        if ($diskSpace['percentage_used'] > 90) {
            $this->telegramService->sendDiskSpaceWarningNotification(
                $diskSpace['path'],
                $diskSpace['percentage_used'],
                $diskSpace['available']
            );
        }

        // Check if we have enough space on the actual disk
        if ($diskSpace['available'] < $file->getSize()) {
            $this->telegramService->sendBackupFailureNotification(
                $app,
                'Insufficient disk space on server. Available: '.round($diskSpace['available'] / 1024 / 1024 / 1024, 2).' GB, Required: '.round($file->getSize() / 1024 / 1024 / 1024, 2).' GB'
            );

            throw new \RuntimeException('Insufficient disk space on server');
        }

        try {
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid().($extension ? '.'.$extension : '');
            Storage::disk('backups')->putFileAs("{$app->id}", $file, $filename);

            return "{$app->id}/{$filename}";
        } catch (\Exception $e) {
            $this->telegramService->sendBackupFailureNotification(
                $app,
                'Failed to store backup file: '.$e->getMessage()
            );

            throw $e;
        }
    }

    public function deleteBackup(string $filePath): bool
    {
        return Storage::disk('backups')->delete($filePath);
    }
}
