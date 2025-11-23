<?php

use App\Models\App;
use App\Models\Backup;
use Illuminate\Support\Carbon;

test('hasBackupSchedule returns true when backup_period is set', function () {
    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    expect($app->hasBackupSchedule())->toBeTrue();
});

test('hasBackupSchedule returns false when backup_period is null', function () {
    $app = App::factory()->create([
        'backup_period' => null,
    ]);

    expect($app->hasBackupSchedule())->toBeFalse();
});

test('isBackupMissed returns true for daily schedule when no backup exists', function () {
    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    expect($app->isBackupMissed())->toBeTrue();
});

test('isBackupMissed returns true for daily schedule when last backup is before today', function () {
    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::yesterday(),
    ]);

    expect($app->isBackupMissed())->toBeTrue();
});

test('isBackupMissed returns false for daily schedule when backup exists today', function () {
    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::today(),
    ]);

    expect($app->isBackupMissed())->toBeFalse();
});

test('isBackupMissed returns false for weekly schedule when not a scheduled day', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15')); // Monday

    $app = App::factory()->create([
        'backup_period' => 'weekly',
        'backup_days' => ['W', 'F'], // Wednesday, Friday
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-10'), // Last Wednesday
    ]);

    expect($app->isBackupMissed())->toBeFalse();

    Carbon::setTestNow();
});

test('isBackupMissed returns true for weekly schedule on scheduled day with no recent backup', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-17')); // Wednesday

    $app = App::factory()->create([
        'backup_period' => 'weekly',
        'backup_days' => ['W', 'F'], // Wednesday, Friday
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-10'), // Last Wednesday (7 days ago)
    ]);

    expect($app->isBackupMissed())->toBeTrue();

    Carbon::setTestNow();
});

test('isBackupMissed returns false for weekly schedule on scheduled day with backup today', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-17')); // Wednesday

    $app = App::factory()->create([
        'backup_period' => 'weekly',
        'backup_days' => ['W', 'F'], // Wednesday, Friday
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::today(),
    ]);

    expect($app->isBackupMissed())->toBeFalse();

    Carbon::setTestNow();
});

test('isBackupMissed returns true for monthly schedule on first day with no backup', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-01')); // First of month

    $app = App::factory()->create([
        'backup_period' => 'monthly',
    ]);

    expect($app->isBackupMissed())->toBeTrue();

    Carbon::setTestNow();
});

test('isBackupMissed returns true for monthly schedule on first day with old backup', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-01')); // First of month

    $app = App::factory()->create([
        'backup_period' => 'monthly',
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2023-12-01'), // Last month
    ]);

    expect($app->isBackupMissed())->toBeTrue();

    Carbon::setTestNow();
});

test('isBackupMissed returns false for monthly schedule on first day with backup today', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-01')); // First of month

    $app = App::factory()->create([
        'backup_period' => 'monthly',
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::today(),
    ]);

    expect($app->isBackupMissed())->toBeFalse();

    Carbon::setTestNow();
});

test('isBackupMissed returns false for monthly schedule when not first day', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15')); // Not first of month

    $app = App::factory()->create([
        'backup_period' => 'monthly',
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2023-12-01'), // Last month
    ]);

    expect($app->isBackupMissed())->toBeFalse();

    Carbon::setTestNow();
});

test('shouldBackupToday returns true for daily schedule', function () {
    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    expect($app->shouldBackupToday())->toBeTrue();
});

test('shouldBackupToday returns true for weekly schedule on scheduled day', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-17')); // Wednesday

    $app = App::factory()->create([
        'backup_period' => 'weekly',
        'backup_days' => ['W', 'F'], // Wednesday, Friday
    ]);

    expect($app->shouldBackupToday())->toBeTrue();

    Carbon::setTestNow();
});

test('shouldBackupToday returns false for weekly schedule on non-scheduled day', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15')); // Monday

    $app = App::factory()->create([
        'backup_period' => 'weekly',
        'backup_days' => ['W', 'F'], // Wednesday, Friday
    ]);

    expect($app->shouldBackupToday())->toBeFalse();

    Carbon::setTestNow();
});

test('shouldBackupToday returns true for monthly schedule on first day', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-01')); // First of month

    $app = App::factory()->create([
        'backup_period' => 'monthly',
    ]);

    expect($app->shouldBackupToday())->toBeTrue();

    Carbon::setTestNow();
});

test('shouldBackupToday returns false for monthly schedule when not first day', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15')); // Not first of month

    $app = App::factory()->create([
        'backup_period' => 'monthly',
    ]);

    expect($app->shouldBackupToday())->toBeFalse();

    Carbon::setTestNow();
});

test('withBackupSchedule scope only returns apps with backup_period', function () {
    App::factory()->create(['backup_period' => null]);
    App::factory()->create(['backup_period' => 'daily']);
    App::factory()->create(['backup_period' => 'weekly']);

    $apps = App::withBackupSchedule()->get();

    expect($apps)->toHaveCount(2);
    expect($apps->pluck('backup_period'))->not->toContain(null);
});
