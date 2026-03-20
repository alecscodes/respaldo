<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ScriptController;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()) && Setting::isRegistrationAllowed(),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('apps', AppController::class);
    Route::get('apps/{app}/backups', [BackupController::class, 'index'])->name('apps.backups');
    Route::post('apps/{app}/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::post('apps/{app}/backups/chunked/init', [BackupController::class, 'initChunkUpload'])->name('backups.chunked.init');
    Route::post('apps/{app}/backups/chunked/upload', [BackupController::class, 'uploadChunk'])->name('backups.chunked.upload');
    Route::post('apps/{app}/backups/chunked/finalize', [BackupController::class, 'finalizeChunkUpload'])->name('backups.chunked.finalize');
    Route::get('apps/{app}/backups/chunked/{uploadId}/status', [BackupController::class, 'chunkUploadStatus'])->name('backups.chunked.status');
    Route::post('apps/{app}/apply-retention', [AppController::class, 'applyRetention'])->name('apps.apply-retention');
    Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::get('script/download', [ScriptController::class, 'download'])->name('script.download');
    Route::get('logs', [LogController::class, 'index'])->name('logs.index');
    Route::delete('logs', [LogController::class, 'destroy'])->name('logs.destroy');
});

require __DIR__.'/settings.php';
