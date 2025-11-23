<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppRequest;
use App\Http\Requests\UpdateAppRequest;
use App\Models\App;
use App\Models\Backup;
use App\Services\BackupRetentionService;
use App\Services\BackupService;
use App\Services\StorageConverter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected BackupRetentionService $retentionService
    ) {}

    public function index(): Response
    {
        $apps = App::where('user_id', auth()->id())
            ->get()
            ->map(fn (App $app) => $this->formatAppData($app));

        return Inertia::render('Apps/Index', ['apps' => $apps]);
    }

    public function store(StoreAppRequest $request): RedirectResponse
    {
        $app = App::create([
            'name' => $request->validated()['name'],
            'storage_size' => $request->validated()['storage_size'],
            'user_id' => auth()->id(),
            'backup_period' => $request->validated()['backup_period'] ?? null,
            'backup_days' => $request->validated()['backup_days'] ?? null,
            'retention_days' => $request->validated()['retention_days'] ?? null,
            'retention_count' => $request->validated()['retention_count'] ?? null,
        ]);

        return redirect()->route('apps.show', $app)->with('success', 'App created successfully.');
    }

    public function show(App $app): Response
    {
        abort_if($app->user_id !== auth()->id(), 403);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Backup> $backups */
        $backups = $app->backups()->latest()->get();

        return Inertia::render('Apps/Show', [
            'app' => $this->formatAppData($app),
            'backups' => $backups->map(fn (Backup $backup) => [
                'id' => $backup->id,
                'filename' => $backup->filename,
                'size' => StorageConverter::bytesToGb($backup->size),
                'size_bytes' => $backup->size,
                'created_at' => $backup->created_at,
            ]),
        ]);
    }

    public function update(UpdateAppRequest $request, App $app): RedirectResponse
    {
        abort_if($app->user_id !== auth()->id(), 403);

        $validated = $request->validated();

        // Clear backup_days if period is not weekly
        if (isset($validated['backup_period']) && $validated['backup_period'] !== 'weekly') {
            $validated['backup_days'] = null;
        }

        $app->update($validated);

        return redirect()->route('apps.show', $app)->with('success', 'App updated successfully.');
    }

    public function destroy(App $app): RedirectResponse
    {
        abort_if($app->user_id !== auth()->id(), 403);

        // Delete all backup files before deleting the app
        /** @var Backup $backup */
        foreach ($app->backups as $backup) {
            $this->backupService->deleteBackup($backup->file_path);
        }

        $app->delete();

        return redirect()->route('apps.index')->with('success', 'App deleted successfully.');
    }

    public function applyRetention(App $app): RedirectResponse
    {
        abort_if($app->user_id !== auth()->id(), 403);

        $result = $this->retentionService->applyRetentionForApp($app);

        if ($result['deleted_count'] > 0) {
            $freedGb = round($result['freed_space'] / 1024 / 1024 / 1024, 2);

            return redirect()->route('apps.show', $app)->with('success', "Deleted {$result['deleted_count']} backup(s), freed {$freedGb} GB.");
        }

        return redirect()->route('apps.show', $app)->with('success', 'No backups were deleted.');
    }

    /**
     * Format app data for Inertia responses.
     */
    private function formatAppData(App $app): array
    {
        $usedSpace = $app->usedSpace();
        $availableSpace = $app->availableSpace();

        return [
            'id' => $app->id,
            'name' => $app->name,
            'storage_size' => $app->storage_size_gb,
            'storage_size_bytes' => $app->storage_size,
            'used_space' => StorageConverter::bytesToGb($usedSpace),
            'used_space_bytes' => $usedSpace,
            'available_space' => StorageConverter::bytesToGb($availableSpace),
            'available_space_bytes' => $availableSpace,
            'backup_period' => $app->backup_period,
            'backup_days' => $app->backup_days,
            'retention_days' => $app->retention_days,
            'retention_count' => $app->retention_count,
            'created_at' => $app->created_at,
        ];
    }
}
