<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [ApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/apps', [ApiController::class, 'apps']);
    Route::post('/apps', [ApiController::class, 'storeApp']);
    Route::get('/apps/{app}/backups', [ApiController::class, 'backups']);
    Route::get('/apps/{app}/space-check', [ApiController::class, 'checkSpace']);
    Route::post('/apps/{app}/backups', [ApiController::class, 'createBackup']);
});
