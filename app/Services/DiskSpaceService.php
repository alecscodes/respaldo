<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DiskSpaceService
{
    /**
     * Get disk space information for the backup volume.
     *
     * @return array{total: int, used: int, available: int, percentage_used: float, path: string}
     */
    public function getBackupDiskSpace(): array
    {
        $backupDisk = Storage::disk('backups');
        $backupPath = $backupDisk->path('');

        // Ensure directory exists
        if (! is_dir($backupPath)) {
            @mkdir($backupPath, 0755, true);
        }

        $totalSpace = disk_total_space($backupPath);
        $freeSpace = disk_free_space($backupPath);

        if ($totalSpace === false || $freeSpace === false) {
            return [
                'total' => 0,
                'used' => 0,
                'available' => 0,
                'percentage_used' => 0.0,
                'path' => $backupPath,
            ];
        }

        $usedSpace = $totalSpace - $freeSpace;
        $percentageUsed = $totalSpace > 0 ? ($usedSpace / $totalSpace) * 100 : 0;

        return [
            'total' => (int) $totalSpace,
            'used' => (int) $usedSpace,
            'available' => (int) $freeSpace,
            'percentage_used' => round($percentageUsed, 2),
            'path' => $backupPath,
        ];
    }
}
