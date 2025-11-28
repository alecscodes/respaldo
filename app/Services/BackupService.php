<?php

namespace App\Services;

use App\Models\App;
use App\Models\ChunkUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupService
{
    public function __construct(
        protected DiskSpaceService $diskSpaceService,
        protected TelegramNotificationService $telegramService,
        protected BackupRetentionService $retentionService,
        protected LogService $logService
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

            $this->logService->warning('system', 'Disk space warning', [
                'path' => $diskSpace['path'],
                'percentage_used' => $diskSpace['percentage_used'],
                'available' => $diskSpace['available'],
            ]);
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

                    $this->logService->critical('backup', 'Insufficient disk space for backup', [
                        'app_id' => $app->id,
                        'app_name' => $app->name,
                        'available' => $diskSpace['available'],
                        'required' => $file->getSize(),
                    ]);

                    throw new \RuntimeException('Insufficient disk space on server');
                }
            }
        }

        try {
            $filename = $this->generateBackupFilename($app, $file);
            Storage::disk('backups')->putFileAs("{$app->id}", $file, $filename);

            $this->logService->info('backup', 'Backup file stored', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'filename' => $filename,
                'size' => $file->getSize(),
            ]);

            return [
                'file_path' => "{$app->id}/{$filename}",
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            $this->telegramService->sendBackupFailureNotification(
                $app,
                'Failed to store backup file: '.$e->getMessage()
            );

            $this->logService->error('backup', 'Backup storage failed', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a backup filename using best practices: app-name-YYYY-MM-DD-HH-mm-ss.tar.gz
     */
    protected function generateBackupFilename(App $app, UploadedFile|string $fileOrName): string
    {
        $appSlug = Str::slug($app->name);
        $datetime = now()->format('Y-m-d-H-i-s');

        // Extract extension from file or filename
        if ($fileOrName instanceof UploadedFile) {
            $originalName = strtolower($fileOrName->getClientOriginalName());
            $extension = str_ends_with($originalName, '.tar.gz')
                ? 'tar.gz'
                : ($fileOrName->getClientOriginalExtension() ?: 'tar.gz');
        } else {
            $originalName = strtolower($fileOrName);
            $extension = str_ends_with($originalName, '.tar.gz')
                ? 'tar.gz'
                : (pathinfo($fileOrName, PATHINFO_EXTENSION) ?: 'tar.gz');
        }

        return "{$appSlug}-{$datetime}.{$extension}";
    }

    public function deleteBackup(string $filePath): bool
    {
        $deleted = Storage::disk('backups')->delete($filePath);

        if ($deleted) {
            $this->logService->info('backup', 'Backup file deleted', ['file_path' => $filePath]);
        }

        return $deleted;
    }

    /**
     * Assemble chunks into final backup file using streaming for memory efficiency.
     *
     * @return array{file_path: string, filename: string}
     */
    public function assembleChunks(ChunkUpload $chunkUpload): array
    {
        $app = $chunkUpload->app;
        $filename = $this->generateBackupFilename($app, $chunkUpload->filename);
        $finalPath = "{$app->id}/{$filename}";
        $chunksDisk = Storage::disk('chunks');
        $backupsDisk = Storage::disk('backups');
        $chunkDir = $chunkUpload->upload_id;

        // Verify all chunks exist before starting assembly
        $missingChunks = [];
        for ($i = 0; $i < $chunkUpload->total_chunks; $i++) {
            $chunkPath = "{$chunkDir}/chunk_{$i}";
            if (! $chunksDisk->exists($chunkPath)) {
                $missingChunks[] = $i;
            } else {
                // Verify chunk size
                $expectedSize = ($i === $chunkUpload->total_chunks - 1)
                    ? ($chunkUpload->total_size - ($i * $chunkUpload->chunk_size))
                    : $chunkUpload->chunk_size;
                $actualSize = $chunksDisk->size($chunkPath);
                if ($actualSize !== $expectedSize) {
                    throw new \RuntimeException("Chunk {$i} size mismatch. Expected: {$expectedSize}, Got: {$actualSize}");
                }
            }
        }

        if (! empty($missingChunks)) {
            throw new \RuntimeException('Missing chunks: '.implode(', ', $missingChunks));
        }

        // Create temp file for assembly
        $tempFile = tmpfile();
        if (! $tempFile) {
            throw new \RuntimeException('Failed to create temporary file');
        }

        try {
            // Stream chunks in order to temp file
            for ($i = 0; $i < $chunkUpload->total_chunks; $i++) {
                $chunkPath = "{$chunkDir}/chunk_{$i}";
                $chunkStream = $chunksDisk->readStream($chunkPath);

                if (! $chunkStream) {
                    throw new \RuntimeException("Failed to read chunk {$i}");
                }

                $bytesCopied = stream_copy_to_stream($chunkStream, $tempFile);
                fclose($chunkStream);

                if ($bytesCopied === false) {
                    throw new \RuntimeException("Failed to copy chunk {$i} to temp file");
                }
            }

            // Rewind temp file and write to final location
            rewind($tempFile);
            $backupsDisk->writeStream($finalPath, $tempFile);
            fclose($tempFile);
            $tempFile = null;

            // Verify file size
            $actualSize = $backupsDisk->size($finalPath);
            if ($actualSize !== $chunkUpload->total_size) {
                $backupsDisk->delete($finalPath);
                throw new \RuntimeException("File size mismatch. Expected: {$chunkUpload->total_size}, Got: {$actualSize}");
            }

            // Cleanup chunk files
            $chunksDisk->deleteDirectory($chunkDir);

            $this->logService->info('backup', 'Chunked backup assembled', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'filename' => $filename,
                'size' => $chunkUpload->total_size,
                'chunks' => $chunkUpload->total_chunks,
            ]);

            return [
                'file_path' => $finalPath,
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            if ($tempFile && is_resource($tempFile)) {
                fclose($tempFile);
            }
            if ($backupsDisk->exists($finalPath)) {
                $backupsDisk->delete($finalPath);
            }
            throw $e;
        }
    }

    /**
     * Store a chunk file using streams for memory efficiency.
     */
    public function storeChunk(ChunkUpload $chunkUpload, UploadedFile $chunkFile, int $chunkIndex): void
    {
        $chunksDisk = Storage::disk('chunks');
        $chunkPath = "{$chunkUpload->upload_id}/chunk_{$chunkIndex}";
        $expectedSize = ($chunkIndex === $chunkUpload->total_chunks - 1)
            ? ($chunkUpload->total_size - ($chunkIndex * $chunkUpload->chunk_size))
            : $chunkUpload->chunk_size;

        if ($chunkFile->getSize() !== $expectedSize) {
            throw new \RuntimeException("Chunk {$chunkIndex} size mismatch. Expected: {$expectedSize}, Got: {$chunkFile->getSize()}");
        }

        $realPath = $chunkFile->getRealPath();
        if ($realPath && file_exists($realPath) && filesize($realPath) > 0) {
            $chunksDisk->writeStream($chunkPath, fopen($realPath, 'rb'));
        } else {
            $chunksDisk->put($chunkPath, file_get_contents($chunkFile->getPathname()) ?: str_repeat("\0", $expectedSize));
        }

        if ($chunksDisk->size($chunkPath) !== $expectedSize) {
            $chunksDisk->delete($chunkPath);
            throw new \RuntimeException("Chunk {$chunkIndex} size mismatch after storage");
        }
    }

    /**
     * Cleanup chunk files and directory.
     */
    public function cleanupChunks(string $uploadId): void
    {
        Storage::disk('chunks')->deleteDirectory($uploadId);
    }
}
