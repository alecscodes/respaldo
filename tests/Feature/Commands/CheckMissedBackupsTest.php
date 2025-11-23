<?php

use App\Models\App;
use App\Models\Backup;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');
});

test('command finds and notifies about missed daily backups', function () {
    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    // No backups exist, so it should be missed
    $result = Artisan::call('backups:check-missed');

    expect($result)->toBe(0);
    $this->artisan('backups:check-missed')
        ->expectsOutput('Found 1 app(s) with missed backups. Notifications sent.')
        ->assertSuccessful();

    Http::assertSent(function ($request) use ($app) {
        $data = $request->data();

        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $data['chat_id'] === 'test-chat-id'
            && str_contains($data['text'], 'Missed Backup Alert')
            && str_contains($data['text'], $app->name)
            && str_contains($data['text'], 'Daily');
    });
});

test('command finds and notifies about missed weekly backups', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-17')); // Wednesday

    $app = App::factory()->create([
        'backup_period' => 'weekly',
        'backup_days' => ['W', 'F'], // Wednesday, Friday
    ]);

    // Last backup was last week
    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-10'), // Last Wednesday
    ]);

    $this->artisan('backups:check-missed')
        ->expectsOutput('Found 1 app(s) with missed backups. Notifications sent.')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($data['text'], 'Weekly');
    });

    Carbon::setTestNow();
});

test('command finds and notifies about missed monthly backups', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-01')); // First of month

    $app = App::factory()->create([
        'backup_period' => 'monthly',
    ]);

    // Last backup was last month
    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2023-12-01'),
    ]);

    $this->artisan('backups:check-missed')
        ->expectsOutput('Found 1 app(s) with missed backups. Notifications sent.')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($data['text'], 'Monthly');
    });

    Carbon::setTestNow();
});

test('command does not notify when backups are up to date', function () {
    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::today(),
    ]);

    $this->artisan('backups:check-missed')
        ->expectsOutput('All scheduled backups are up to date.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('command handles multiple apps with missed backups', function () {
    $app1 = App::factory()->create(['backup_period' => 'daily']);
    $app2 = App::factory()->create(['backup_period' => 'daily']);

    // No backups for either app

    $this->artisan('backups:check-missed')
        ->expectsOutput('Found 2 app(s) with missed backups. Notifications sent.')
        ->assertSuccessful();

    Http::assertSentCount(2);
});

test('command ignores apps without backup schedule', function () {
    App::factory()->create(['backup_period' => null]);
    App::factory()->create(['backup_period' => 'daily']);

    // No backups for daily app
    $this->artisan('backups:check-missed')
        ->expectsOutput('Found 1 app(s) with missed backups. Notifications sent.')
        ->assertSuccessful();
});

test('command sends notification with correct format', function () {
    $app = App::factory()->create([
        'name' => 'Test App',
        'backup_period' => 'daily',
    ]);

    Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::yesterday(),
    ]);

    $this->artisan('backups:check-missed')
        ->assertSuccessful();

    Http::assertSent(function ($request) use ($app) {
        $data = $request->data();
        $text = $data['text'];

        return str_contains($text, '⏰')
            && str_contains($text, 'Missed Backup Alert')
            && str_contains($text, $app->name)
            && str_contains($text, 'Daily')
            && str_contains($text, 'Last Backup:')
            && $data['parse_mode'] === 'HTML';
    });
});

test('command handles notification failure gracefully', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => false], 500),
    ]);

    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    // Command should still complete successfully even if notification fails
    $this->artisan('backups:check-missed')
        ->expectsOutput('Found 1 app(s) with missed backups. Notifications sent.')
        ->assertSuccessful();
});

test('command shows never when no backup exists', function () {
    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    $this->artisan('backups:check-missed')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($data['text'], 'Last Backup: Never');
    });
});
