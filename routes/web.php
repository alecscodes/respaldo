<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ScriptController;
use App\Models\Backup;
use App\Services\StorageConverter;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    // Block access to homepage if homepage is disabled
    if (! \App\Models\Setting::isHomepageAllowed()) {
        abort(403, 'Homepage is currently disabled.');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()) && \App\Models\Setting::isRegistrationAllowed(),
    ]);
})->name('home');

Route::get('dashboard', function () {
    $diskSpaceService = app(\App\Services\DiskSpaceService::class);
    $diskSpace = $diskSpaceService->getBackupDiskSpace();

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
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('apps', AppController::class);
    Route::get('apps/{app}/backups', [BackupController::class, 'index'])->name('apps.backups');
    Route::post('apps/{app}/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::get('script/download', [ScriptController::class, 'download'])->name('script.download');
});

require __DIR__.'/settings.php';
