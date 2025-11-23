<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScriptController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()) && \App\Models\Setting::isRegistrationAllowed(),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('apps', AppController::class);
    Route::get('apps/{app}/backups', [BackupController::class, 'index'])->name('apps.backups');
    Route::post('apps/{app}/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::post('apps/{app}/apply-retention', [AppController::class, 'applyRetention'])->name('apps.apply-retention');
    Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::get('script/download', [ScriptController::class, 'download'])->name('script.download');
});

require __DIR__.'/settings.php';
