<?php

namespace App\Services;

use App\Models\App;
use App\Models\Backup;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class TelegramNotificationService
{
    /**
     * Send a notification to Telegram.
     */
    public function sendNotification(string $message): bool
    {
        $botToken = Setting::get('telegram_bot_token');
        $chatId = Setting::get('telegram_chat_id');

        if (! $botToken || ! $chatId) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Send a backup failure notification.
     */
    public function sendBackupFailureNotification(App $app, string $reason): bool
    {
        $message = "❌ <b>Backup Failed</b>\n\n";
        $message .= "App: {$app->name}\n";
        $message .= "Reason: {$reason}\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

        return $this->sendNotification($message);
    }

    /**
     * Send a storage insufficient notification.
     */
    public function sendStorageInsufficientNotification(App $app, int $requiredSize, int $availableSize): bool
    {
        $requiredGb = round($requiredSize / 1024 / 1024 / 1024, 2);
        $availableGb = round($availableSize / 1024 / 1024 / 1024, 2);

        $message = "⚠️ <b>Insufficient Storage</b>\n\n";
        $message .= "App: {$app->name}\n";
        $message .= "Required: {$requiredGb} GB\n";
        $message .= "Available: {$availableGb} GB\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

        return $this->sendNotification($message);
    }

    /**
     * Send a backup success notification (optional, for important backups).
     */
    public function sendBackupSuccessNotification(App $app, Backup $backup): bool
    {
        $sizeGb = round($backup->size / 1024 / 1024 / 1024, 2);

        $message = "✅ <b>Backup Created</b>\n\n";
        $message .= "App: {$app->name}\n";
        $message .= "File: {$backup->filename}\n";
        $message .= "Size: {$sizeGb} GB\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

        return $this->sendNotification($message);
    }

    /**
     * Send a disk space warning notification.
     */
    public function sendDiskSpaceWarningNotification(string $path, float $percentageUsed, int $availableBytes): bool
    {
        $availableGb = round($availableBytes / 1024 / 1024 / 1024, 2);

        $message = "⚠️ <b>Low Disk Space Warning</b>\n\n";
        $message .= "Path: {$path}\n";
        $message .= "Used: {$percentageUsed}%\n";
        $message .= "Available: {$availableGb} GB\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

        return $this->sendNotification($message);
    }

    /**
     * Send a missed backup notification.
     */
    public function sendMissedBackupNotification(App $app): bool
    {
        $lastBackupTime = $app->backups()->latest()->value('created_at');
        $lastBackupTimeFormatted = $lastBackupTime
            ? $lastBackupTime->format('Y-m-d H:i:s')
            : 'Never';

        $message = "⏰ <b>Missed Backup Alert</b>\n\n";
        $message .= "App: {$app->name}\n";
        $message .= 'Schedule: '.ucfirst($app->backup_period ?? 'unknown')."\n";
        $message .= "Last Backup: {$lastBackupTimeFormatted}\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

        return $this->sendNotification($message);
    }
}
