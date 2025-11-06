<?php

namespace App\Services;

class StorageConverter
{
    /**
     * Convert GB to bytes.
     */
    public static function gbToBytes(float $gb): int
    {
        return (int) ($gb * 1024 * 1024 * 1024);
    }

    /**
     * Convert bytes to GB.
     */
    public static function bytesToGb(int $bytes): float
    {
        return $bytes / (1024 * 1024 * 1024);
    }

    /**
     * Format bytes to human-readable string (GB, MB, KB).
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    /**
     * Format GB to human-readable string.
     */
    public static function formatGb(float $gb): string
    {
        return number_format($gb, 2).' GB';
    }
}
