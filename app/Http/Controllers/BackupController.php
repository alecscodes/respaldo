<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBackupRequest;
use App\Models\App;
use App\Models\Backup;
use App\Services\BackupService;
use App\Services\StorageConverter;
use App\Services\TelegramNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected TelegramNotificationService $telegramService
    ) {}

    public function index(App $app): \Illuminate\Http\JsonResponse
    {
        abort_if($app->user_id !== auth()->id(), 403);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Backup> $backups */
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

    public function store(StoreBackupRequest $request, App $app): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_if($app->user_id !== auth()->id(), 403);

        $file = $request->file('file');

        if (! $file) {
            return $request->wantsJson()
                ? response()->json(['error' => 'No file uploaded.'], 400)
                : redirect()->back()->withErrors(['file' => 'No file uploaded.']);
        }

        $fileSize = $file->getSize();

        if (! $app->canBackup($fileSize)) {
            $this->telegramService->sendStorageInsufficientNotification(
                $app,
                $fileSize,
                $app->availableSpace()
            );

            return $request->wantsJson()
                ? response()->json(['error' => 'Not enough storage space available.'], 400)
                : redirect()->back()->with('error', 'Not enough storage space available.');
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
        } catch (\Exception $e) {
            $this->telegramService->sendBackupFailureNotification(
                $app,
                'Failed to create backup: '.$e->getMessage()
            );

            throw $e;
        }

        return $request->wantsJson()
            ? response()->json(['message' => 'Backup created successfully.'], 201)
            : redirect()->back()->with('success', 'Backup created successfully.');
    }

    public function download(Backup $backup): BinaryFileResponse|StreamedResponse
    {
        abort_if($backup->user_id !== auth()->id(), 403);
        abort_unless(Storage::disk('backups')->exists($backup->file_path), 404, 'Backup file not found.');

        return Storage::disk('backups')->download($backup->file_path, $backup->filename);
    }

    public function destroy(Backup $backup): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_if($backup->user_id !== auth()->id(), 403);

        $this->backupService->deleteBackup($backup->file_path);
        $backup->delete();

        return request()->wantsJson()
            ? response()->json(['message' => 'Backup deleted successfully.'])
            : redirect()->back()->with('success', 'Backup deleted successfully.');
    }
}
