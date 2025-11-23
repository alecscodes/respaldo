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
        protected TelegramNotificationService $telegramService,
        protected BackupRetentionService $retentionService
    ) {}

    /**
     * @return array{file_path: string, filename: string}
     */
    public function createBackup(App $app, UploadedFile $file): array
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
            // Try to free space by running retention cleanup for this specific app first
            $this->retentionService->applyRetentionForApp($app);

            // Recheck disk space after cleanup
            $diskSpace = $this->diskSpaceService->getBackupDiskSpace();

            // If still insufficient, try cleaning all apps
            if ($diskSpace['available'] < $file->getSize()) {
                $this->retentionService->applyRetentionForAllApps();

                // Recheck disk space after cleanup
                $diskSpace = $this->diskSpaceService->getBackupDiskSpace();

                if ($diskSpace['available'] < $file->getSize()) {
                    $this->telegramService->sendBackupFailureNotification(
                        $app,
                        'Insufficient disk space on server. Available: '.round($diskSpace['available'] / 1024 / 1024 / 1024, 2).' GB, Required: '.round($file->getSize() / 1024 / 1024 / 1024, 2).' GB'
                    );

                    throw new \RuntimeException('Insufficient disk space on server');
                }
            }
        }

        try {
            $filename = $this->generateBackupFilename($app, $file);
            Storage::disk('backups')->putFileAs("{$app->id}", $file, $filename);

            return [
                'file_path' => "{$app->id}/{$filename}",
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            $this->telegramService->sendBackupFailureNotification(
                $app,
                'Failed to store backup file: '.$e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Generate a backup filename using best practices: app-name-YYYY-MM-DD-HH-mm-ss.tar.gz
     */
    protected function generateBackupFilename(App $app, UploadedFile $file): string
    {
        $appSlug = Str::slug($app->name);
        $datetime = now()->format('Y-m-d-H-i-s');

        // Determine file extension - prefer .tar.gz, fallback to original extension
        $originalName = strtolower($file->getClientOriginalName());
        $extension = str_ends_with($originalName, '.tar.gz')
            ? 'tar.gz'
            : ($file->getClientOriginalExtension() ?: 'tar.gz');

        return "{$appSlug}-{$datetime}.{$extension}";
    }

    public function deleteBackup(string $filePath): bool
    {
        return Storage::disk('backups')->delete($filePath);
    }
}
