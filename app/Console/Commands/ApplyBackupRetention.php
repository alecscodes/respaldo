<?php

namespace App\Console\Commands;

use App\Models\App;
use App\Services\BackupRetentionService;
use Illuminate\Console\Command;

class ApplyBackupRetention extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backups:apply-retention {--app= : Apply retention to a specific app ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply backup retention policies to delete old backups';

    /**
     * Execute the console command.
     */
    public function handle(BackupRetentionService $retentionService): int
    {
        $appId = $this->option('app');

        if ($appId) {
            $app = App::find($appId);

            if (! $app) {
                $this->error("App with ID {$appId} not found.");

                return Command::FAILURE;
            }

            if (! $app->hasRetentionPolicy()) {
                $this->warn("App '{$app->name}' does not have a retention policy configured.");

                return Command::SUCCESS;
            }

            $this->info("Applying retention policy for app: {$app->name}");

            $result = $retentionService->applyRetentionForApp($app);

            if ($result['deleted_count'] > 0) {
                $freedGb = round($result['freed_space'] / 1024 / 1024 / 1024, 2);
                $this->info("Deleted {$result['deleted_count']} backup(s), freed {$freedGb} GB.");
            } else {
                $this->info('No backups were deleted.');
            }

            return Command::SUCCESS;
        }

        $this->info('Applying retention policies for all apps...');

        $result = $retentionService->applyRetentionForAllApps();

        if ($result['total_deleted'] > 0) {
            $freedGb = round($result['total_freed_space'] / 1024 / 1024 / 1024, 2);
            $this->info("Deleted {$result['total_deleted']} backup(s) across {$result['apps_processed']} app(s), freed {$freedGb} GB total.");
        } else {
            $this->info('No backups were deleted.');
        }

        return Command::SUCCESS;
    }
}
