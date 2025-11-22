<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppRequest;
use App\Http\Requests\StoreBackupRequest;
use App\Models\App;
use App\Models\Backup;
use App\Services\BackupService;
use App\Services\IpBanService;
use App\Services\StorageConverter;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected IpBanService $ipBanService,
        protected TelegramNotificationService $telegramService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->ipBanService->recordFailedLogin($request);

            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'token' => $user->createToken('respaldo-cli')->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function apps(Request $request): JsonResponse
    {
        $apps = App::where('user_id', $request->user()->id)->get()->map(fn (App $app) => [
            'id' => $app->id,
            'name' => $app->name,
            'storage_size' => StorageConverter::bytesToGb($app->storage_size),
            'used_space' => StorageConverter::bytesToGb($app->usedSpace()),
            'available_space' => StorageConverter::bytesToGb($app->availableSpace()),
        ]);

        return response()->json($apps);
    }

    public function storeApp(StoreAppRequest $request): JsonResponse
    {
        $app = App::create([
            'name' => $request->validated()['name'],
            'storage_size' => $request->validated()['storage_size'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $app->id,
            'name' => $app->name,
            'storage_size' => StorageConverter::bytesToGb($app->storage_size),
        ], 201);
    }

    public function backups(Request $request, App $app): JsonResponse
    {
        abort_if($app->user_id !== $request->user()->id, 403);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Backup> $backups */
        $backups = $app->backups()->latest()->get();

        return response()->json(
            $backups->map(fn (Backup $backup) => [
                'id' => $backup->id,
                'filename' => $backup->filename,
                'size' => StorageConverter::bytesToGb($backup->size),
                'created_at' => $backup->created_at,
            ])
        );
    }

    public function checkSpace(Request $request, App $app): JsonResponse
    {
        abort_if($app->user_id !== $request->user()->id, 403);

        $size = (int) $request->query('size', 0);

        return response()->json([
            'available' => $app->canBackup($size),
            'available_space' => StorageConverter::bytesToGb($app->availableSpace()),
            'required_space' => StorageConverter::bytesToGb($size),
        ]);
    }

    public function createBackup(StoreBackupRequest $request, App $app): JsonResponse
    {
        abort_if($app->user_id !== $request->user()->id, 403);

        $file = $request->file('file');
        $fileSize = $file->getSize();

        if (! $app->canBackup($fileSize)) {
            $this->telegramService->sendStorageInsufficientNotification(
                $app,
                $fileSize,
                $app->availableSpace()
            );

            abort(400, 'Not enough storage space available.');
        }

        try {
            $backupData = $this->backupService->createBackup($app, $file);
            $backup = Backup::create([
                'app_id' => $app->id,
                'filename' => $backupData['filename'],
                'file_path' => $backupData['file_path'],
                'size' => $fileSize,
                'user_id' => $request->user()->id,
            ]);
        } catch (\Exception $e) {
            $this->telegramService->sendBackupFailureNotification(
                $app,
                'Failed to create backup: '.$e->getMessage()
            );

            throw $e;
        }

        return response()->json([
            'id' => $backup->id,
            'filename' => $backup->filename,
            'size' => StorageConverter::bytesToGb($backup->size),
            'message' => 'Backup created successfully.',
        ], 201);
    }

    public function downloadBackup(Request $request, Backup $backup): BinaryFileResponse|StreamedResponse
    {
        abort_if($backup->user_id !== $request->user()->id, 403);
        abort_unless(Storage::disk('backups')->exists($backup->file_path), 404, 'Backup file not found.');

        return Storage::disk('backups')->download($backup->file_path, $backup->filename);
    }
}
