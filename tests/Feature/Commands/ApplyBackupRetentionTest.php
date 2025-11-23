<?php

use App\Models\App;
use App\Models\Backup;
use App\Models\Setting;
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

test('command applies retention for all apps', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app1 = App::factory()->create([
        'name' => 'App 1',
        'retention_days' => 7,
    ]);

    $app2 = App::factory()->create([
        'name' => 'App 2',
        'retention_count' => 2,
    ]);

    $oldBackup1 = Backup::factory()->create([
        'app_id' => $app1->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 100,
    ]);

    $oldBackup2 = Backup::factory()->create([
        'app_id' => $app2->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 50,
    ]);

    $recentBackup = Backup::factory()->create([
        'app_id' => $app2->id,
        'created_at' => Carbon::today(),
        'size' => 1024 * 1024 * 50,
    ]);

    Storage::disk('backups')->put($oldBackup1->file_path, 'fake content');
    Storage::disk('backups')->put($oldBackup2->file_path, 'fake content');
    Storage::disk('backups')->put($recentBackup->file_path, 'fake content');

    // Create 3 backups for app2, so retention_count of 2 will delete 1
    $recentBackup2 = Backup::factory()->create([
        'app_id' => $app2->id,
        'created_at' => Carbon::parse('2024-01-10'),
        'size' => 1024 * 1024 * 50,
    ]);

    Storage::disk('backups')->put($recentBackup2->file_path, 'fake content');

    $this->artisan('backups:apply-retention')
        ->expectsOutput('Applying retention policies for all apps...')
        ->expectsOutputToContain('Deleted')
        ->assertSuccessful();

    expect(Backup::find($oldBackup1->id))->toBeNull();
    expect(Backup::find($oldBackup2->id))->toBeNull();
    expect(Backup::find($recentBackup->id))->not->toBeNull();
    expect(Backup::find($recentBackup2->id))->not->toBeNull();

    Carbon::setTestNow();
});

test('command applies retention for specific app', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app = App::factory()->create([
        'name' => 'Test App',
        'retention_days' => 7,
    ]);

    $oldBackup = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 100,
    ]);

    Storage::disk('backups')->put($oldBackup->file_path, 'fake content');

    $this->artisan('backups:apply-retention', ['--app' => $app->id])
        ->expectsOutput("Applying retention policy for app: {$app->name}")
        ->expectsOutputToContain('Deleted')
        ->assertSuccessful();

    expect(Backup::find($oldBackup->id))->toBeNull();

    Carbon::setTestNow();
});

test('command shows message when no backups are deleted', function () {
    $app = App::factory()->create([
        'retention_days' => 7,
    ]);

    $recentBackup = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::today(),
    ]);

    Storage::disk('backups')->put($recentBackup->file_path, 'fake content');

    $this->artisan('backups:apply-retention', ['--app' => $app->id])
        ->expectsOutput("Applying retention policy for app: {$app->name}")
        ->expectsOutput('No backups were deleted.')
        ->assertSuccessful();
});

test('command shows warning when app has no retention policy', function () {
    $app = App::factory()->create([
        'retention_days' => null,
        'retention_count' => null,
    ]);

    $this->artisan('backups:apply-retention', ['--app' => $app->id])
        ->expectsOutput("App '{$app->name}' does not have a retention policy configured.")
        ->assertSuccessful();
});

test('command returns failure when app does not exist', function () {
    $this->artisan('backups:apply-retention', ['--app' => 99999])
        ->expectsOutput('App with ID 99999 not found.')
        ->assertFailed();
});

test('command handles apps without retention policies correctly', function () {
    $appWithRetention = App::factory()->create(['retention_days' => 7]);
    $appWithoutRetention = App::factory()->create([
        'retention_days' => null,
        'retention_count' => null,
    ]);

    $oldBackup = Backup::factory()->create([
        'app_id' => $appWithRetention->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 100,
    ]);

    Storage::disk('backups')->put($oldBackup->file_path, 'fake content');

    $this->artisan('backups:apply-retention')
        ->expectsOutput('Applying retention policies for all apps...')
        ->expectsOutputToContain('Deleted')
        ->assertSuccessful();

    expect(Backup::find($oldBackup->id))->toBeNull();
});

test('command shows correct freed space information', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15'));

    $app = App::factory()->create([
        'retention_days' => 7,
    ]);

    $oldBackup1 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-01'),
        'size' => 1024 * 1024 * 1024, // 1 GB
    ]);

    $oldBackup2 = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-05'),
        'size' => 512 * 1024 * 1024, // 0.5 GB
    ]);

    Storage::disk('backups')->put($oldBackup1->file_path, 'fake content');
    Storage::disk('backups')->put($oldBackup2->file_path, 'fake content');

    $this->artisan('backups:apply-retention', ['--app' => $app->id])
        ->expectsOutputToContain('freed')
        ->assertSuccessful();

    Carbon::setTestNow();
});
