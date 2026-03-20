<?php

use App\Models\App;
use App\Models\Backup;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');
});

test('sends missed backup notification with app details', function () {
    $app = App::factory()->create([
        'name' => 'Test App',
        'backup_period' => 'daily',
    ]);

    $service = app(TelegramNotificationService::class);
    $result = $service->sendMissedBackupNotification($app);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) use ($app) {
        $data = $request->data();

        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $data['chat_id'] === 'test-chat-id'
            && str_contains($data['text'], '⏰')
            && str_contains($data['text'], 'Missed Backup Alert')
            && str_contains($data['text'], $app->name)
            && str_contains($data['text'], 'Daily')
            && str_contains($data['text'], 'Last Backup: Never')
            && $data['parse_mode'] === 'HTML';
    });
});

test('sends missed backup notification with last backup time', function () {
    $app = App::factory()->create([
        'name' => 'Test App',
        'backup_period' => 'weekly',
    ]);

    $backup = Backup::factory()->create([
        'app_id' => $app->id,
        'created_at' => Carbon::parse('2024-01-10 14:30:00'),
    ]);

    $service = app(TelegramNotificationService::class);
    $service->sendMissedBackupNotification($app);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($data['text'], 'Last Backup: 2024-01-10 14:30:00')
            && str_contains($data['text'], 'Weekly');
    });
});

test('does not send notification when telegram is not configured', function () {
    Setting::set('telegram_bot_token', null);
    Setting::set('telegram_chat_id', null);

    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    $service = app(TelegramNotificationService::class);
    $result = $service->sendMissedBackupNotification($app);

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

test('handles notification failure gracefully', function () {
    // Simulate a network exception
    Http::fake(function () {
        throw new Exception('Network error');
    });

    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    $service = app(TelegramNotificationService::class);
    $result = $service->sendMissedBackupNotification($app);

    // Exception should be caught and return false
    expect($result)->toBeFalse();
});

test('notification includes correct schedule type', function () {
    $dailyApp = App::factory()->create(['backup_period' => 'daily']);
    $weeklyApp = App::factory()->create(['backup_period' => 'weekly']);
    $monthlyApp = App::factory()->create(['backup_period' => 'monthly']);

    $service = app(TelegramNotificationService::class);

    $service->sendMissedBackupNotification($dailyApp);
    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Daily');
    });

    Http::fake(); // Reset
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $service->sendMissedBackupNotification($weeklyApp);
    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Weekly');
    });

    Http::fake(); // Reset
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $service->sendMissedBackupNotification($monthlyApp);
    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Monthly');
    });
});

test('notification includes current timestamp', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15 10:30:00'));

    $app = App::factory()->create([
        'backup_period' => 'daily',
    ]);

    $service = app(TelegramNotificationService::class);
    $service->sendMissedBackupNotification($app);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($data['text'], 'Time: 2024-01-15 10:30:00');
    });

    Carbon::setTestNow();
});
