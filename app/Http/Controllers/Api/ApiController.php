<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppRequest;
use App\Http\Requests\StoreBackupRequest;
use App\Models\App;
use App\Models\Backup;
use App\Models\User;
use App\Services\BackupRetentionService;
use App\Services\BackupService;
use App\Services\IpBanService;
use App\Services\ScriptGeneratorService;
use App\Services\StorageConverter;
use App\Services\TelegramNotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected IpBanService $ipBanService,
        protected TelegramNotificationService $telegramService,
        protected BackupRetentionService $retentionService,
        protected ScriptGeneratorService $scriptGeneratorService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->ipBanService->recordFailedLogin($request);

            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        $this->log('info', 'api', 'API login successful', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

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

        $this->log('info', 'api', 'App created via API', [
            'app_id' => $app->id,
            'app_name' => $app->name,
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
        if ($app->user_id !== $request->user()->id) {
            $this->log('warning', 'security', 'Unauthorized API backup list access', ['app_id' => $app->id]);
            abort(403);
        }

        /** @var Collection<int, Backup> $backups */
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
        if ($app->user_id !== $request->user()->id) {
            $this->log('warning', 'security', 'Unauthorized API space check', ['app_id' => $app->id]);
            abort(403);
        }

        $size = (int) $request->query('size', 0);

        // Try retention cleanup if space is not available
        if (! $app->canBackup($size)) {
            $this->retentionService->applyRetentionForApp($app);
            $app->refresh();

            // Send notification if still not available after cleanup
            if (! $app->canBackup($size)) {
                $this->telegramService->sendStorageInsufficientNotification(
                    $app,
                    $size,
                    $app->availableSpace()
                );
            }
        }

        return response()->json([
            'available' => $app->canBackup($size),
            'available_space' => StorageConverter::bytesToGb($app->availableSpace()),
            'required_space' => StorageConverter::bytesToGb($size),
        ]);
    }

    public function createBackup(StoreBackupRequest $request, App $app): JsonResponse
    {
        if ($app->user_id !== $request->user()->id) {
            $this->log('warning', 'security', 'Unauthorized API backup creation attempt', ['app_id' => $app->id]);
            abort(403);
        }

        $file = $request->file('file');
        $fileSize = $file->getSize();

        if (! $app->canBackup($fileSize)) {
            // Try to free space by running retention cleanup for this app
            $this->retentionService->applyRetentionForApp($app);
            $app->refresh();

            if (! $app->canBackup($fileSize)) {
                $this->telegramService->sendStorageInsufficientNotification(
                    $app,
                    $fileSize,
                    $app->availableSpace()
                );

                abort(400, 'Not enough storage space available.');
            }
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

            $this->log('info', 'api', 'Backup created via API', [
                'backup_id' => $backup->id,
                'app_id' => $app->id,
                'app_name' => $app->name,
                'filename' => $backup->filename,
                'size' => $fileSize,
            ]);
        } catch (\Exception $e) {
            $this->telegramService->sendBackupFailureNotification(
                $app,
                'Failed to create backup: '.$e->getMessage()
            );

            $this->log('error', 'api', 'Backup creation failed via API', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'error' => $e->getMessage(),
            ]);

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
        if ($backup->user_id !== $request->user()->id) {
            $this->log('warning', 'security', 'Unauthorized API backup download attempt', ['backup_id' => $backup->id]);
            abort(403);
        }
        abort_unless(Storage::disk('backups')->exists($backup->file_path), 404, 'Backup file not found.');

        $this->log('info', 'api', 'Backup downloaded via API', [
            'backup_id' => $backup->id,
            'filename' => $backup->filename,
        ]);

        return Storage::disk('backups')->download($backup->file_path, $backup->filename);
    }

    public function scriptVersion(Request $request): JsonResponse
    {
        $version = $this->scriptGeneratorService->getScriptVersion();

        return response()->json([
            'version' => $version,
        ]);
    }

    public function scriptDownload(Request $request): Response
    {
        $script = $this->scriptGeneratorService->generateScript($request->user(), config('app.url'));

        return response($script, 200, [
            'Content-Type' => 'application/x-sh',
            'Content-Disposition' => 'attachment; filename="respaldo.sh"',
            'X-Executable' => 'true',
        ]);
    }
}
