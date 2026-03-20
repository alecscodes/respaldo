<?php

use App\Models\App;
use App\Models\Backup;
use App\Models\Setting;
use App\Services\BackupRetentionService;
use App\Services\LogService;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('backups');

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');
});

test('applyRetentionForApp returns empty result when app has no retention policy', function () {
    $app = App::factory()->create([
        'retention_days' => null,
        'retention_count' => null,
    ]);

    $service = new BackupRetentionService(
        app(TelegramNotificationService::class),
        app(LogService::class)
    );

    $result = $service->applyRetentionForApp($app);

    expect($result['deleted_count'])->toBe(0);
    expect($result['freed_space'])->toBe(0);
});

test('applyRetentionForApp deletes backups older than retention_days', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app = App::factory()->create([
        'retention_days' => 7,
    ]);

    $oldBackup = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-01'), // 14 days ago
        'size' => 1024 * 1024 * 100, // 100 MB
    ]);

    $recentBackup = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-10'), // 5 days ago
        'size' => 1024 * 1024 * 50, // 50 MB
    ]);

    // Create fake files
    Storage::disk('backups')->put($oldBackup->file_path, 'fake content');
    Storage::disk('backups')->put($recentBackup->file_path, 'fake content');

    $service = new BackupRetentionService(
        app(TelegramNotificationService::class),
        app(LogService::class)
    );

    $result = $service->applyRetentionForApp($app);

    expect($result['deleted_count'])->toBe(1);
    expect($result['freed_space'])->toBe($oldBackup->size);
    expect(Backup::find($oldBackup->id))->toBeNull();
    expect(Backup::find($recentBackup->id))->not->toBeNull();
    expect(Storage::disk('backups')->exists($oldBackup->file_path))->toBeFalse();
    expect(Storage::disk('backups')->exists($recentBackup->file_path))->toBeTrue();

    Carbon::setTestNow();
});

test('applyRetentionForApp respects retention_count and keeps newest backups', function () {
    $app = App::factory()->create([
        'retention_count' => 3,
    ]);

    $backups = Backup::factory()->count(5)->create([
        'app_id' => $app->id,
        'size' => 1024 * 1024 * 100, // 100 MB each
    ])->sortByDesc('created_at');

    // Create fake files
    foreach ($backups as $backup) {
        Storage::disk('backups')->put($backup->file_path, 'fake content');
    }

    $service = new BackupRetentionService(
        app(TelegramNotificationService::class),
        app(LogService::class)
    );

    $result = $service->applyRetentionForApp($app);

    expect($result['deleted_count'])->toBe(2);
    expect($result['freed_space'])->toBe(2 * 1024 * 1024 * 100); // 2 backups * 100 MB
    expect(Backup::where('app_id', $app->id)->count())->toBe(3);
});

test('applyRetentionForApp sends notification when backups are deleted', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app = App::factory()->create([
        'retention_days' => 7,
    ]);

    $oldBackup = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 100,
    ]);

    Storage::disk('backups')->put($oldBackup->file_path, 'fake content');

    $service = new BackupRetentionService(
        app(TelegramNotificationService::class),
        app(LogService::class)
    );

    $service->applyRetentionForApp($app);

    Http::assertSent(function ($request) use ($app) {
        $data = $request->data();

        return str_contains($data['text'], 'Backup Retention Cleanup')
            && str_contains($data['text'], $app->name)
            && str_contains($data['text'], 'Deleted: 1 backup(s)');
    });

    Carbon::setTestNow();
});

test('applyRetentionForApp does not send notification when no backups are deleted', function () {
    $app = App::factory()->create([
        'retention_days' => 7,
    ]);

    $recentBackup = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::today(),
    ]);

    Storage::disk('backups')->put($recentBackup->file_path, 'fake content');

    $service = new BackupRetentionService(
        app(TelegramNotificationService::class),
        app(LogService::class)
    );

    $service->applyRetentionForApp($app);

    Http::assertNothingSent();
});

test('applyRetentionForAllApps processes all apps with retention policies', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app1 = App::factory()->create(['retention_days' => 7]);
    $app2 = App::factory()->create(['retention_count' => 2]);
    $app3 = App::factory()->create(['retention_days' => null, 'retention_count' => null]);

    $oldBackup1 = Backup::factory()->create([
        'app_id' => $app1->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 100,
    ]);

    // Create 3 backups for app2, so retention_count of 2 will delete 1
    $oldBackup2 = Backup::factory()->create([
        'app_id' => $app2->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 50,
    ]);

    $recentBackup1 = Backup::factory()->create([
        'app_id' => $app2->id,
        'created_at' => Carbon::parse('2024-01-10'),
        'size' => 1024 * 1024 * 50,
    ]);

    $recentBackup2 = Backup::factory()->create([
        'app_id' => $app2->id,
        'created_at' => Carbon::today(),
        'size' => 1024 * 1024 * 50,
    ]);

    Storage::disk('backups')->put($oldBackup1->file_path, 'fake content');
    Storage::disk('backups')->put($oldBackup2->file_path, 'fake content');
    Storage::disk('backups')->put($recentBackup1->file_path, 'fake content');
    Storage::disk('backups')->put($recentBackup2->file_path, 'fake content');

    $service = new BackupRetentionService(
        app(TelegramNotificationService::class),
        app(LogService::class)
    );

    $result = $service->applyRetentionForAllApps();

    expect($result['total_deleted'])->toBe(2);
    expect($result['apps_processed'])->toBe(2);
    expect($result['total_freed_space'])->toBe(150 * 1024 * 1024); // 150 MB

    Carbon::setTestNow();
});

test('applyRetentionForApp handles missing files gracefully', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app = App::factory()->create([
        'retention_days' => 7,
    ]);

    $oldBackup = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 100,
    ]);

    // Don't create the file - simulate missing file

    $service = new BackupRetentionService(
        app(TelegramNotificationService::class),
        app(LogService::class)
    );

    $result = $service->applyRetentionForApp($app);

    // Should still delete the database record even if file is missing
    expect($result['deleted_count'])->toBe(1);
    expect(Backup::find($oldBackup->id))->toBeNull();

    Carbon::setTestNow();
});
