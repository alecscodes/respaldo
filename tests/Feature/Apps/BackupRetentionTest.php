<?php

use App\Models\App;
use App\Models\Backup;
use Illuminate\Support\Carbon;

test('hasRetentionPolicy returns false when no retention is configured', function () {
    $app = App::factory()->create([
        'retention_days' => null,
        'retention_count' => null,
    ]);

    expect($app->hasRetentionPolicy())->toBeFalse();
});

test('hasRetentionPolicy returns true when retention_days is set', function () {
    $app = App::factory()->create([
        'retention_days' => 30,
        'retention_count' => null,
    ]);

    expect($app->hasRetentionPolicy())->toBeTrue();
});

test('hasRetentionPolicy returns true when retention_count is set', function () {
    $app = App::factory()->create([
        'retention_days' => null,
        'retention_count' => 10,
    ]);

    expect($app->hasRetentionPolicy())->toBeTrue();
});

test('hasRetentionPolicy returns true when both retention policies are set', function () {
    $app = App::factory()->create([
        'retention_days' => 30,
        'retention_count' => 10,
    ]);

    expect($app->hasRetentionPolicy())->toBeTrue();
});

test('backupsToDeleteQuery returns empty when no backups exist', function () {
    $app = App::factory()->create([
        'retention_days' => 7,
    ]);

    expect($app->backupsToDeleteQuery()->count())->toBe(0);
});

test('getBackupsToDelete returns backups older than retention_days', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app = App::factory()->create([
        'retention_days' => 7,
    ]);

    // Create backups: 2 old (should be deleted), 2 recent (should be kept)
    $oldBackup1 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-01'), // 14 days ago
    ]);

    $oldBackup2 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-05'), // 10 days ago
    ]);

    $recentBackup1 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-10'), // 5 days ago
    ]);

    $recentBackup2 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-14'), // 1 day ago
    ]);

    $toDelete = $app->backupsToDeleteQuery()->get();

    expect($toDelete)->toHaveCount(2);
    expect($toDelete->pluck('id'))->toContain($oldBackup1->id);
    expect($toDelete->pluck('id'))->toContain($oldBackup2->id);
    expect($toDelete->pluck('id'))->not->toContain($recentBackup1->id);
    expect($toDelete->pluck('id'))->not->toContain($recentBackup2->id);

    Carbon::setTestNow();
});

test('getBackupsToDelete respects retention_count and keeps newest backups', function () {
    $app = App::factory()->create([
        'retention_count' => 3,
    ]);

    // Create 5 backups
    $backups = Backup::factory()->count(5)->create([
        'app_id' => $app->id,
    ])->sortByDesc('created_at');

    $toDelete = $app->backupsToDeleteQuery()->get();

    // Should delete 2 oldest backups (keep 3 newest)
    expect($toDelete)->toHaveCount(2);
    expect($toDelete->pluck('id'))->toContain($backups->last()->id);
    expect($toDelete->pluck('id'))->toContain($backups->skip(3)->first()->id);
});

test('getBackupsToDelete combines retention_days and retention_count correctly', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app = App::factory()->create([
        'retention_days' => 7,
        'retention_count' => 3,
    ]);

    // Create backups: 2 old (beyond 7 days), 3 recent (within 7 days)
    $oldBackup1 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-01'), // 14 days ago
    ]);

    $oldBackup2 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-05'), // 10 days ago
    ]);

    $recentBackup1 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-10'), // 5 days ago
    ]);

    $recentBackup2 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-12'), // 3 days ago
    ]);

    $recentBackup3 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-14'), // 1 day ago
    ]);

    $toDelete = $app->backupsToDeleteQuery()->get();

    // Should delete old backups (2) that are beyond retention_days
    // But we must keep at least 3 backups (retention_count), so we only delete the 2 old ones
    // We have 5 backups total: 2 old + 3 recent. After deleting 2 old, we have 3 recent (meets retention_count)
    expect($toDelete)->toHaveCount(2);
    expect($toDelete->pluck('id'))->toContain($oldBackup1->id);
    expect($toDelete->pluck('id'))->toContain($oldBackup2->id);
    // recentBackup1 should NOT be deleted because we need to keep at least 3 backups
    expect($toDelete->pluck('id'))->not->toContain($recentBackup1->id);

    Carbon::setTestNow();
});

test('getBackupsToDelete keeps at least retention_count backups even if older than retention_days', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app = App::factory()->create([
        'retention_days' => 5,
        'retention_count' => 3,
    ]);

    // Create 3 backups, all older than 5 days
    $backup1 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-01'), // 14 days ago
    ]);

    $backup2 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-05'), // 10 days ago
    ]);

    $backup3 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-08'), // 7 days ago
    ]);

    $toDelete = $app->backupsToDeleteQuery()->get();

    // All are older than 5 days, but we must keep at least 3, so nothing should be deleted
    expect($toDelete)->toBeEmpty();

    Carbon::setTestNow();
});

test('getBackupsToDelete returns empty when retention_count is greater than total backups', function () {
    $app = App::factory()->create([
        'retention_count' => 10,
    ]);

    Backup::factory()->count(5)->create([
        'app_id' => $app->id,
    ]);

    $toDelete = $app->backupsToDeleteQuery()->get();

    expect($toDelete)->toBeEmpty();
});
