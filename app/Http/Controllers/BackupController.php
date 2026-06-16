<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeChunkUploadRequest;
use App\Http\Requests\InitChunkUploadRequest;
use App\Http\Requests\StoreBackupRequest;
use App\Http\Requests\UploadChunkRequest;
use App\Models\App;
use App\Models\Backup;
use App\Models\ChunkUpload;
use App\Services\BackupRetentionService;
use App\Services\BackupService;
use App\Services\StorageConverter;
use App\Services\TelegramNotificationService;
use App\Support\FlashToast;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected TelegramNotificationService $telegramService,
        protected BackupRetentionService $retentionService
    ) {}

    public function index(App $app): JsonResponse
    {
        $this->authorizeApp($app);

        /** @var Collection<int, Backup> $backups */
        $backups = $app->backups()->latest()->get();

        return response()->json(
            $backups->map(fn (Backup $backup) => [
                'id' => $backup->id,
                'filename' => $backup->filename,
                'size' => StorageConverter::bytesToGb($backup->size),
                'size_bytes' => $backup->size,
                'created_at' => $backup->created_at,
            ])
        );
    }

    public function store(StoreBackupRequest $request, App $app): RedirectResponse|JsonResponse
    {
        $this->authorizeApp($app);

        $file = $request->file('file');
        if (! $file) {
            return $this->errorResponse($request, 'No file uploaded.');
        }

        $fileSize = $file->getSize();
        if (! $this->ensureStorageSpace($app, $fileSize)) {
            $this->telegramService->sendStorageInsufficientNotification(
                $app,
                $fileSize,
                $app->availableSpace()
            );

            return $this->errorResponse($request, 'Not enough storage space available.');
        }

        try {
            $backupData = $this->backupService->createBackup($app, $file);
            $backup = Backup::create([
                'app_id' => $app->id,
                'filename' => $backupData['filename'],
                'file_path' => $backupData['file_path'],
                'size' => $fileSize,
                'user_id' => auth()->id(),
            ]);

            $this->log('info', 'backup', 'Backup created', [
                'backup_id' => $backup->id,
                'app_id' => $app->id,
                'app_name' => $app->name,
                'filename' => $backup->filename,
                'size' => $fileSize,
            ]);
        } catch (\Exception $e) {
            $this->telegramService->sendBackupFailureNotification($app, 'Failed to create backup: '.$e->getMessage());
            $this->log('error', 'backup', 'Backup creation failed', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Backup created successfully.'], 201);
        }

        FlashToast::success('Backup created successfully.');

        return redirect()->back();
    }

    public function download(Backup $backup): BinaryFileResponse|StreamedResponse
    {
        if ($backup->user_id !== auth()->id()) {
            $this->log('warning', 'security', 'Unauthorized backup download attempt', ['backup_id' => $backup->id]);
            abort(403);
        }

        abort_unless(Storage::disk('backups')->exists($backup->file_path), 404, 'Backup file not found.');

        $this->log('info', 'backup', 'Backup downloaded', [
            'backup_id' => $backup->id,
            'filename' => $backup->filename,
        ]);

        return Storage::disk('backups')->download($backup->file_path, $backup->filename);
    }

    public function destroy(Backup $backup): RedirectResponse|JsonResponse
    {
        if ($backup->user_id !== auth()->id()) {
            $this->log('warning', 'security', 'Unauthorized backup deletion attempt', ['backup_id' => $backup->id]);
            abort(403);
        }

        $this->backupService->deleteBackup($backup->file_path);
        $backup->delete();

        $this->log('info', 'backup', 'Backup deleted', [
            'backup_id' => $backup->id,
            'filename' => $backup->filename,
        ]);

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Backup deleted successfully.']);
        }

        FlashToast::success('Backup deleted successfully.');

        return redirect()->back();
    }

    public function initChunkUpload(InitChunkUploadRequest $request, App $app): JsonResponse
    {
        $this->authorizeApp($app);

        $totalSize = $request->integer('total_size');
        $chunkSize = $request->integer('chunk_size');
        $filename = $request->string('filename')->toString();

        if (! $this->ensureStorageSpace($app, $totalSize)) {
            $this->telegramService->sendStorageInsufficientNotification(
                $app,
                $totalSize,
                $app->availableSpace()
            );

            return response()->json(['error' => 'Not enough storage space available.'], 400);
        }

        $chunkUpload = ChunkUpload::create([
            'upload_id' => ChunkUpload::generateUploadId(),
            'app_id' => $app->id,
            'user_id' => auth()->id(),
            'filename' => $filename,
            'total_size' => $totalSize,
            'total_chunks' => (int) ceil($totalSize / $chunkSize),
            'chunk_size' => $chunkSize,
            'status' => 'in_progress',
            'expires_at' => now()->addHours(24),
        ]);

        $this->log('info', 'backup', 'Chunk upload initialized', [
            'upload_id' => $chunkUpload->upload_id,
            'app_id' => $app->id,
            'filename' => $filename,
            'total_size' => $totalSize,
        ]);

        return response()->json([
            'upload_id' => $chunkUpload->upload_id,
            'total_chunks' => $chunkUpload->total_chunks,
            'chunk_size' => $chunkSize,
        ], 201);
    }

    public function uploadChunk(UploadChunkRequest $request, App $app): JsonResponse
    {
        $this->authorizeApp($app);

        $chunkUpload = $this->findChunkUpload($request->string('upload_id')->toString(), $app);
        $chunkIndex = $request->integer('chunk_index');

        if ($chunkUpload->status !== 'in_progress') {
            return response()->json(['error' => 'Upload session is not in progress.'], 400);
        }

        if ($chunkUpload->hasChunk($chunkIndex)) {
            return response()->json([
                'message' => 'Chunk already uploaded.',
                'progress' => $chunkUpload->getProgressPercentage(),
            ]);
        }

        try {
            // Store chunk file first (outside transaction to minimize lock time)
            $this->backupService->storeChunk($chunkUpload, $request->file('chunk'), $chunkIndex);

            // Update database with pessimistic locking and retry on deadlocks
            retry(3, function () use ($chunkUpload, $chunkIndex) {
                DB::transaction(function () use ($chunkUpload, $chunkIndex) {
                    $chunkUpload = ChunkUpload::where('id', $chunkUpload->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($chunkUpload->status !== 'in_progress') {
                        throw new \RuntimeException('Upload session is not in progress.');
                    }

                    if (! $chunkUpload->hasChunk($chunkIndex)) {
                        $chunkUpload->markChunkUploaded($chunkIndex);
                        $chunkUpload->save();
                    }
                }, 10);
            }, 100); // 100ms delay between retries

            $chunkUpload->refresh();

            return response()->json([
                'message' => 'Chunk uploaded successfully.',
                'progress' => $chunkUpload->getProgressPercentage(),
                'uploaded_chunks' => count($chunkUpload->uploaded_chunks ?? []),
                'total_chunks' => $chunkUpload->total_chunks,
            ]);
        } catch (\Exception $e) {
            $this->log('error', 'backup', 'Chunk upload failed', [
                'upload_id' => $chunkUpload->upload_id,
                'chunk_index' => $chunkIndex,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to upload chunk: '.$e->getMessage()], 500);
        }
    }

    public function finalizeChunkUpload(FinalizeChunkUploadRequest $request, App $app): JsonResponse
    {
        $this->authorizeApp($app);

        $chunkUpload = $this->findChunkUpload($request->string('upload_id')->toString(), $app);

        if ($chunkUpload->status !== 'in_progress') {
            return response()->json(['error' => 'Upload session is not in progress.'], 400);
        }

        // Refresh to ensure we have latest state
        $chunkUpload->refresh();

        // Verify chunks exist on disk, not just in database
        $chunksDisk = Storage::disk('chunks');
        $chunkDir = $chunkUpload->upload_id;
        $missingChunks = [];

        for ($i = 0; $i < $chunkUpload->total_chunks; $i++) {
            $chunkPath = "{$chunkDir}/chunk_{$i}";
            if (! $chunksDisk->exists($chunkPath)) {
                $missingChunks[] = $i;
            }
        }

        if (! empty($missingChunks)) {
            // Update database to reflect actual state
            $uploadedChunks = array_values(array_diff(range(0, $chunkUpload->total_chunks - 1), $missingChunks));
            $chunkUpload->update(['uploaded_chunks' => $uploadedChunks]);

            return response()->json([
                'error' => 'Not all chunks have been uploaded.',
                'missing_chunks' => $missingChunks,
            ], 400);
        }

        if (! $chunkUpload->isComplete()) {
            return response()->json([
                'error' => 'Not all chunks have been uploaded.',
                'missing_chunks' => $chunkUpload->getMissingChunks(),
            ], 400);
        }

        try {
            $backup = DB::transaction(function () use ($chunkUpload, $app) {
                $backupData = $this->backupService->assembleChunks($chunkUpload);

                $backup = Backup::create([
                    'app_id' => $app->id,
                    'filename' => $backupData['filename'],
                    'file_path' => $backupData['file_path'],
                    'size' => $chunkUpload->total_size,
                    'user_id' => auth()->id(),
                ]);

                $chunkUpload->update([
                    'status' => 'completed',
                    'file_path' => $backupData['file_path'],
                ]);

                return $backup;
            });

            $this->log('info', 'backup', 'Chunked backup created', [
                'backup_id' => $backup->id,
                'app_id' => $app->id,
                'filename' => $backup->filename,
            ]);

            return response()->json(['message' => 'Backup created successfully.', 'backup_id' => $backup->id], 201);
        } catch (\Exception $e) {
            $chunkUpload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->telegramService->sendBackupFailureNotification($app, 'Failed to finalize chunked backup: '.$e->getMessage());
            $this->log('error', 'backup', 'Chunked backup finalization failed', [
                'upload_id' => $chunkUpload->upload_id,
                'app_id' => $app->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to finalize backup: '.$e->getMessage()], 500);
        }
    }

    public function chunkUploadStatus(App $app, string $uploadId): JsonResponse
    {
        $this->authorizeApp($app);

        $chunkUpload = $this->findChunkUpload($uploadId, $app);

        return response()->json([
            'upload_id' => $chunkUpload->upload_id,
            'status' => $chunkUpload->status,
            'progress' => $chunkUpload->getProgressPercentage(),
            'uploaded_chunks' => count($chunkUpload->uploaded_chunks ?? []),
            'total_chunks' => $chunkUpload->total_chunks,
            'missing_chunks' => $chunkUpload->getMissingChunks(),
            'error_message' => $chunkUpload->error_message,
        ]);
    }

    /**
     * Authorize that the user owns the app.
     */
    protected function authorizeApp(App $app): void
    {
        if ($app->user_id !== auth()->id()) {
            $this->log('warning', 'security', 'Unauthorized app access attempt', ['app_id' => $app->id]);
            abort(403);
        }
    }

    /**
     * Find chunk upload and authorize access.
     */
    protected function findChunkUpload(string $uploadId, App $app): ChunkUpload
    {
        return ChunkUpload::where('upload_id', $uploadId)
            ->where('app_id', $app->id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    /**
     * Ensure storage space is available, applying retention if needed.
     */
    protected function ensureStorageSpace(App $app, int $requiredSize): bool
    {
        if ($app->canBackup($requiredSize)) {
            return true;
        }

        $this->retentionService->applyRetentionForApp($app);
        $app->refresh();

        return $app->canBackup($requiredSize);
    }

    /**
     * Return error response based on request type.
     */
    protected function errorResponse(
        Request $request,
        string $message,
        array $data = []
    ): RedirectResponse|JsonResponse {
        if ($request->wantsJson()) {
            return response()->json(array_merge(['error' => $message], $data), 400);
        }

        FlashToast::error($message);

        return redirect()->back();
    }
}
