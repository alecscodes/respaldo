<?php

use App\Http\Controllers\Settings\BannedIpsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RegistrationController;
use App\Http\Controllers\Settings\TelegramController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\Settings\UpdateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/registration', [RegistrationController::class, 'edit'])->name('registration.edit');
    Route::patch('settings/registration', [RegistrationController::class, 'update'])->name('registration.update');

    Route::get('settings/banned-ips', [BannedIpsController::class, 'index'])->name('banned-ips.index');
    Route::delete('settings/banned-ips/unban', [BannedIpsController::class, 'destroy'])->name('banned-ips.destroy');

    Route::get('settings/updates/check', [UpdateController::class, 'check'])->name('updates.check');
    Route::post('settings/updates/update', [UpdateController::class, 'update'])->name('updates.update');

    Route::get('settings/telegram', [TelegramController::class, 'edit'])->name('telegram.edit');
    Route::patch('settings/telegram', [TelegramController::class, 'update'])->name('telegram.update');
    Route::post('settings/telegram/test', [TelegramController::class, 'test'])->name('telegram.test');
});
