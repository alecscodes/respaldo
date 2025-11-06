<?php

namespace App\Services;

use App\Models\App;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupService
{
    public function createBackup(App $app, UploadedFile $file, int $userId): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid().($extension ? '.'.$extension : '');
        Storage::disk('backups')->putFileAs("{$app->id}", $file, $filename);

        return "{$app->id}/{$filename}";
    }

    public function deleteBackup(string $filePath): bool
    {
        return Storage::disk('backups')->delete($filePath);
    }
}
