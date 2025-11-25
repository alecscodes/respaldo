<?php

use App\Models\Log;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

test('logs:cleanup deletes old logs based on retention periods', function () {
    // Create logs with different levels and ages
    $debugLog = Log::factory()->create([
        'level' => 'debug',
        'created_at' => now()->subDays(10), // Older than 7 days retention
    ]);

    $infoLog = Log::factory()->create([
        'level' => 'info',
        'created_at' => now()->subDays(35), // Older than 30 days retention
    ]);

    $errorLog = Log::factory()->create([
        'level' => 'error',
        'created_at' => now()->subDays(5), // Within 90 days retention
    ]);

    $criticalLog = Log::factory()->create([
        'level' => 'critical',
        'created_at' => now()->subDays(5), // Within 365 days retention
    ]);

    expect(Log::count())->toBe(4);

    Artisan::call('logs:cleanup');

    // debug and info should be deleted, error and critical should remain
    // Note: The cleanup command may create a system log, so we check specific logs
    expect(Log::where('id', $debugLog->id)->exists())->toBeFalse();
    expect(Log::where('id', $infoLog->id)->exists())->toBeFalse();
    expect(Log::where('id', $errorLog->id)->exists())->toBeTrue();
    expect(Log::where('id', $criticalLog->id)->exists())->toBeTrue();
});

test('logs:cleanup respects custom retention settings', function () {
    // Set custom retention for debug logs to 15 days
    Setting::set('log_retention_days_debug', 15);

    // Create a debug log that's 10 days old (should be kept with 15 day retention)
    $keptLog = Log::factory()->create([
        'level' => 'debug',
        'created_at' => now()->subDays(10),
    ]);

    // Create a debug log that's 20 days old (should be deleted)
    $deletedLog = Log::factory()->create([
        'level' => 'debug',
        'created_at' => now()->subDays(20),
    ]);

    Artisan::call('logs:cleanup');

    // Only the 20-day-old log should be deleted
    expect(Log::where('id', $keptLog->id)->exists())->toBeTrue();
    expect(Log::where('id', $deletedLog->id)->exists())->toBeFalse();
});

test('logs:cleanup dry-run mode shows what would be deleted', function () {
    Log::factory()->create([
        'level' => 'debug',
        'created_at' => now()->subDays(10),
    ]);

    $initialCount = Log::count();

    Artisan::call('logs:cleanup', ['--dry-run' => true]);

    $output = Artisan::output();

    expect($output)->toContain('DRY RUN MODE');
    expect($output)->toContain('would be deleted');
    expect(Log::count())->toBe($initialCount); // No logs should be deleted
});

test('logs:cleanup handles no logs to clean up', function () {
    // Create only recent logs that shouldn't be deleted
    Log::factory()->create([
        'level' => 'debug',
        'created_at' => now()->subDays(5), // Within 7 days retention
    ]);

    Artisan::call('logs:cleanup');

    $output = Artisan::output();

    expect($output)->toContain('No logs to clean up');
    expect(Log::count())->toBe(1);
});

test('logs:cleanup deletes logs in batches', function () {
    // Create more logs than batch size to test batch deletion
    Log::factory()->count(1500)->create([
        'level' => 'debug',
        'created_at' => now()->subDays(10), // Older than 7 days retention
    ]);

    expect(Log::count())->toBe(1500);

    Artisan::call('logs:cleanup');

    // All debug logs should be deleted
    expect(Log::where('level', 'debug')->count())->toBe(0);
});

test('logs:cleanup keeps logs within retention period', function () {
    // Create logs that are just within retention periods
    Log::factory()->create([
        'level' => 'debug',
        'created_at' => now()->subDays(6), // Within 7 days retention
    ]);

    Log::factory()->create([
        'level' => 'info',
        'created_at' => now()->subDays(29), // Within 30 days retention
    ]);

    Log::factory()->create([
        'level' => 'error',
        'created_at' => now()->subDays(89), // Within 90 days retention
    ]);

    Artisan::call('logs:cleanup');

    // All logs should remain
    expect(Log::count())->toBe(3);
});
