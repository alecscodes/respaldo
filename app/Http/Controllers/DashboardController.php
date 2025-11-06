<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\DiskSpaceService;
use App\Services\StorageConverter;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected DiskSpaceService $diskSpaceService) {}

    public function index(): Response
    {
        $diskSpace = $this->diskSpaceService->getBackupDiskSpace();

        $latestBackups = Backup::where('user_id', auth()->id())
            ->with('app:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($backup) => [
                'id' => $backup->id,
                'filename' => $backup->filename,
                'size' => StorageConverter::bytesToGb($backup->size),
                'size_bytes' => $backup->size,
                'created_at' => $backup->created_at->toIso8601String(),
                'app' => [
                    'id' => $backup->app->id,
                    'name' => $backup->app->name,
                ],
            ]);

        return Inertia::render('Dashboard', [
            'backupDiskSpace' => [
                'total' => $diskSpace['total'],
                'used' => $diskSpace['used'],
                'available' => $diskSpace['available'],
                'percentage_used' => $diskSpace['percentage_used'],
                'path' => $diskSpace['path'],
            ],
            'latestBackups' => $latestBackups,
        ]);
    }
}
