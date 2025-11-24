<?php

namespace App\Console\Commands;

use App\Models\ChunkUpload;
use App\Services\BackupService;
use Illuminate\Console\Command;

class CleanupStaleChunkUploads extends Command
{
    protected $signature = 'backups:cleanup-stale-chunks';

    protected $description = 'Clean up stale chunk uploads that have expired or failed';

    public function __construct(
        protected BackupService $backupService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Cleaning up stale chunk uploads...');

        // Clean up expired or failed uploads
        $staleUploads = ChunkUpload::where(function ($query) {
            $query->where('expires_at', '<', now())
                ->orWhere('status', 'failed')
                ->orWhere(function ($q) {
                    $q->where('status', 'in_progress')
                        ->where('created_at', '<', now()->subHours(48));
                });
        })->get();

        $cleaned = 0;
        foreach ($staleUploads as $upload) {
            try {
                // Cleanup chunk files
                $this->backupService->cleanupChunks($upload->upload_id);

                // Delete the record
                $upload->delete();
                $cleaned++;
            } catch (\Exception $e) {
                $this->warn("Failed to cleanup upload {$upload->upload_id}: {$e->getMessage()}");
            }
        }

        $this->info("Cleaned up {$cleaned} stale chunk upload(s).");

        return Command::SUCCESS;
    }
}
