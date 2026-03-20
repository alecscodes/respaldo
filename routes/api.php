<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [ApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/apps', [ApiController::class, 'apps']);
    Route::post('/apps', [ApiController::class, 'storeApp']);
    Route::get('/apps/{app}/backups', [ApiController::class, 'backups']);
    Route::get('/apps/{app}/space-check', [ApiController::class, 'checkSpace']);
    Route::post('/apps/{app}/backups', [ApiController::class, 'createBackup']);
    Route::post('/apps/{app}/backups/chunked/init', [BackupController::class, 'initChunkUpload']);
    Route::post('/apps/{app}/backups/chunked/upload', [BackupController::class, 'uploadChunk']);
    Route::post('/apps/{app}/backups/chunked/finalize', [BackupController::class, 'finalizeChunkUpload']);
    Route::get('/apps/{app}/backups/chunked/{uploadId}/status', [BackupController::class, 'chunkUploadStatus']);
    Route::get('/backups/{backup}/download', [ApiController::class, 'downloadBackup']);
    Route::get('/script/version', [ApiController::class, 'scriptVersion']);
    Route::get('/script/download', [ApiController::class, 'scriptDownload']);
});
