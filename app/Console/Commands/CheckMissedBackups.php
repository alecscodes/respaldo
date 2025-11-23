<?php

namespace App\Console\Commands;

use App\Models\App;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

class CheckMissedBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backups:check-missed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for missed backups and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(TelegramNotificationService $telegramService): int
    {
        $missedCount = App::withBackupSchedule()
            ->with('backups:id,app_id,created_at')
            ->get()
            ->filter(fn (App $app) => $app->isBackupMissed())
            ->each(fn (App $app) => $telegramService->sendMissedBackupNotification($app))
            ->count();

        $this->info($missedCount > 0
            ? "Found {$missedCount} app(s) with missed backups. Notifications sent."
            : 'All scheduled backups are up to date.'
        );

        return Command::SUCCESS;
    }
}
