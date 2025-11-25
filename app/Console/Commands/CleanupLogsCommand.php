<?php

namespace App\Console\Commands;

use App\Enums\LogLevel;
use App\Models\Log;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CleanupLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old logs based on configurable retention periods';

    /**
     * Default retention periods in days for each log level.
     * Critical logs are kept longer than debug/info logs.
     *
     * @var array<string, int>
     */
    private const array DEFAULT_RETENTION_DAYS = [
        'emergency' => 365,  // 1 year
        'alert' => 365,      // 1 year
        'critical' => 365,   // 1 year
        'error' => 90,       // 3 months
        'warning' => 60,     // 2 months
        'notice' => 30,      // 1 month
        'info' => 30,        // 1 month
        'debug' => 7,        // 1 week
    ];

    /**
     * Batch size for deletion to avoid long-running queries.
     */
    private const int BATCH_SIZE = 1000;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No logs will be deleted');
        }

        $this->newLine();

        $levelStats = collect(LogLevel::cases())
            ->mapWithKeys(fn (LogLevel $level) => $this->processLevel($level, $isDryRun))
            ->filter()
            ->toArray();

        $totalDeleted = array_sum(array_column($levelStats, 'count'));

        $this->displayResults($levelStats, $totalDeleted, $isDryRun);

        if (! $isDryRun && $totalDeleted > 0) {
            \Illuminate\Support\Facades\Log::channel('database')->info('Logs cleanup completed', [
                'category' => 'system',
                'deleted_count' => $totalDeleted,
                'level_stats' => $levelStats,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Process cleanup for a specific log level.
     */
    private function processLevel(LogLevel $level, bool $isDryRun): array
    {
        $levelValue = $level->value;
        $retentionDays = $this->getRetentionDays($levelValue);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $query = Log::where('level', $levelValue)
            ->where('created_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            return [];
        }

        if (! $isDryRun) {
            $query->chunkById(self::BATCH_SIZE, fn ($logs) => $logs->each->delete());
        }

        return [
            $levelValue => [
                'count' => $count,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Get retention days for a log level from settings or use default.
     */
    private function getRetentionDays(string $level): int
    {
        return (int) Setting::get("log_retention_days_{$level}", self::DEFAULT_RETENTION_DAYS[$level] ?? 30);
    }

    /**
     * Display cleanup results.
     */
    private function displayResults(array $levelStats, int $totalDeleted, bool $isDryRun): void
    {
        if (empty($levelStats)) {
            $this->info('✅ No logs to clean up.');

            return;
        }

        $this->info($isDryRun ? '📊 Logs that would be deleted:' : '🗑️  Logs deleted:');
        $this->newLine();

        $this->table(
            ['Level', 'Count', 'Retention (days)', 'Cutoff Date'],
            collect($levelStats)->map(fn (array $stats, string $level) => [
                strtoupper($level),
                number_format($stats['count']),
                $stats['retention_days'],
                $stats['cutoff_date'],
            ])->values()->toArray()
        );

        $this->newLine();
        $this->info("Total: {$totalDeleted} logs ".($isDryRun ? 'would be deleted' : 'deleted'));
    }
}
