<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppRequest;
use App\Http\Requests\UpdateAppRequest;
use App\Models\App;
use App\Models\Backup;
use App\Services\BackupService;
use App\Services\StorageConverter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppController extends Controller
{
    public function __construct(protected BackupService $backupService) {}

    public function index(): Response
    {
        $apps = App::where('user_id', auth()->id())->get()->map(fn (App $app) => [
            'id' => $app->id,
            'name' => $app->name,
            'storage_size' => $app->storage_size_gb,
            'storage_size_bytes' => $app->storage_size,
            'used_space' => StorageConverter::bytesToGb($app->usedSpace()),
            'used_space_bytes' => $app->usedSpace(),
            'available_space' => StorageConverter::bytesToGb($app->availableSpace()),
            'available_space_bytes' => $app->availableSpace(),
            'created_at' => $app->created_at,
        ]);

        return Inertia::render('Apps/Index', ['apps' => $apps]);
    }

    public function store(StoreAppRequest $request): RedirectResponse
    {
        $app = App::create([
            'name' => $request->validated()['name'],
            'storage_size' => $request->validated()['storage_size'],
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('apps.show', $app)->with('success', 'App created successfully.');
    }

    public function show(App $app): Response
    {
        abort_if($app->user_id !== auth()->id(), 403);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Backup> $backups */
        $backups = $app->backups()->latest()->get();

        return Inertia::render('Apps/Show', [
            'app' => [
                'id' => $app->id,
                'name' => $app->name,
                'storage_size' => $app->storage_size_gb,
                'storage_size_bytes' => $app->storage_size,
                'used_space' => StorageConverter::bytesToGb($app->usedSpace()),
                'used_space_bytes' => $app->usedSpace(),
                'available_space' => StorageConverter::bytesToGb($app->availableSpace()),
                'available_space_bytes' => $app->availableSpace(),
            ],
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

        $app->update($request->validated());

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
}
