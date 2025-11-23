<?php

namespace App\Services;

use App\Models\App;
use Illuminate\Support\Facades\Storage;

class BackupRetentionService
{
    public function __construct(
        protected TelegramNotificationService $telegramService,
        protected LogService $logService
    ) {}

    /**
     * Apply retention policy for a specific app.
     */
    public function applyRetentionForApp(App $app): array
    {
        if (! $app->hasRetentionPolicy()) {
            return ['deleted_count' => 0, 'freed_space' => 0];
        }

        $query = $app->backupsToDeleteQuery();
        $backups = $query->get();

        if ($backups->isEmpty()) {
            return ['deleted_count' => 0, 'freed_space' => 0];
        }

        $freedSpace = $backups->sum('size');

        // Delete files and records
        $backups->each(function ($backup) {
            try {
                if (Storage::disk('backups')->exists($backup->file_path)) {
                    Storage::disk('backups')->delete($backup->file_path);
                }
                $backup->delete();
            } catch (\Exception $e) {
                $this->logService->error('retention', 'Failed to delete backup during retention', [
                    'backup_id' => $backup->id,
                    'file_path' => $backup->file_path,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $deletedCount = $backups->count();

        if ($deletedCount > 0) {
            $this->telegramService->sendRetentionCleanupNotification($app, $deletedCount, $freedSpace);

            $this->logService->info('retention', 'Retention policy applied', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'deleted_count' => $deletedCount,
                'freed_space' => $freedSpace,
            ]);
        }

        return ['deleted_count' => $deletedCount, 'freed_space' => $freedSpace];
    }

    /**
     * Apply retention policies for all apps.
     */
    public function applyRetentionForAllApps(): array
    {
        $apps = App::where(function ($query) {
            $query->whereNotNull('retention_days')->orWhereNotNull('retention_count');
        })->get();

        $totalDeleted = 0;
        $totalFreedSpace = 0;
        $appsProcessed = 0;

        foreach ($apps as $app) {
            $result = $this->applyRetentionForApp($app);
            $totalDeleted += $result['deleted_count'];
            $totalFreedSpace += $result['freed_space'];

            if ($result['deleted_count'] > 0) {
                $appsProcessed++;
            }
        }

        return [
            'total_deleted' => $totalDeleted,
            'total_freed_space' => $totalFreedSpace,
            'apps_processed' => $appsProcessed,
        ];
    }
}
