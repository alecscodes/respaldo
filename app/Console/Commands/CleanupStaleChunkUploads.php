<?php

namespace App\Console\Commands;

use App\Models\ChunkUpload;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupStaleChunkUploads extends Command
{
    protected $signature = 'backups:cleanup-stale-chunks';

    protected $description = 'Clean up stale chunk uploads that have expired or failed';

    private const int STUCK_UPLOAD_HOURS = 48;

    private const int COMPLETED_UPLOAD_DAYS = 7;

    private const int ORPHANED_DIRECTORY_HOURS = 24;

    public function __construct(
        protected BackupService $backupService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Cleaning up stale chunk uploads...');

        $cleaned = $this->cleanupStaleUploads();
        $orphanedCleaned = $this->cleanupOrphanedChunks();

        $this->info("Cleaned up {$cleaned} stale chunk upload(s) and {$orphanedCleaned} orphaned chunk directory(ies).");

        return Command::SUCCESS;
    }

    /**
     * Clean up stale chunk uploads from database.
     */
    protected function cleanupStaleUploads(): int
    {
        $staleUploads = ChunkUpload::where(function ($query) {
            $query->where('expires_at', '<', now())
                ->orWhere('status', 'failed')
                ->orWhere(function ($q) {
                    $q->where('status', 'in_progress')
                        ->where('created_at', '<', now()->subHours(self::STUCK_UPLOAD_HOURS));
                })
                ->orWhere(function ($q) {
                    $q->where('status', 'completed')
                        ->where('updated_at', '<', now()->subDays(self::COMPLETED_UPLOAD_DAYS));
                });
        })->get();

        $cleaned = 0;
        $staleUploads->each(function (ChunkUpload $upload) use (&$cleaned) {
            try {
                $this->backupService->cleanupChunks($upload->upload_id);
                $upload->delete();
                $cleaned++;
            } catch (\Exception $e) {
                $this->warn("Failed to cleanup upload {$upload->upload_id}: {$e->getMessage()}");
            }
        });

        return $cleaned;
    }

    /**
     * Clean up orphaned chunk directories that don't have a database record.
     */
    protected function cleanupOrphanedChunks(): int
    {
        $chunksDisk = Storage::disk('chunks');
        $validUploadIds = ChunkUpload::pluck('upload_id')->toArray();

        $cleaned = 0;
        collect($chunksDisk->directories())
            ->filter(function (string $directory) use ($validUploadIds) {
                $uploadId = basename($directory);

                return ! in_array($uploadId, $validUploadIds, true);
            })
            ->filter(function (string $directory) use ($chunksDisk) {
                $path = $chunksDisk->path($directory);
                $ageInHours = (time() - filemtime($path)) / 3600;

                return $ageInHours >= self::ORPHANED_DIRECTORY_HOURS;
            })
            ->each(function (string $directory) use ($chunksDisk, &$cleaned) {
                try {
                    $chunksDisk->deleteDirectory($directory);
                    $cleaned++;
                } catch (\Exception $e) {
                    $this->warn("Failed to cleanup orphaned directory {$directory}: {$e->getMessage()}");
                }
            });

        return $cleaned;
    }
}
