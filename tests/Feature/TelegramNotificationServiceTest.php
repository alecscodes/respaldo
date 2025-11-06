<?php

use App\Models\App;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Http;

test('sendNotification sends message to Telegram when configured', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    $service = new TelegramNotificationService;

    expect($service->sendNotification('Test message'))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $request->data()['chat_id'] === 'test-chat-id'
            && $request->data()['text'] === 'Test message';
    });
});

test('sendNotification returns false when not configured', function () {
    Setting::set('telegram_bot_token', null);
    Setting::set('telegram_chat_id', null);

    $service = new TelegramNotificationService;

    expect($service->sendNotification('Test message'))->toBeFalse();
});

test('sendBackupFailureNotification sends correct message', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    $app = App::factory()->create([
        'name' => 'Test App',
    ]);

    $service = new TelegramNotificationService;

    expect($service->sendBackupFailureNotification($app, 'Test reason'))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Backup Failed')
            && str_contains($request->data()['text'], 'Test App')
            && str_contains($request->data()['text'], 'Test reason');
    });
});

test('sendStorageInsufficientNotification sends correct message', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    $app = App::factory()->create([
        'name' => 'Test App',
    ]);

    $service = new TelegramNotificationService;

    expect($service->sendStorageInsufficientNotification($app, 1073741824, 536870912))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Insufficient Storage')
            && str_contains($request->data()['text'], 'Test App');
    });
});

test('sendDiskSpaceWarningNotification sends correct message', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    $service = new TelegramNotificationService;

    expect($service->sendDiskSpaceWarningNotification('/path/to/backups', 95.5, 1073741824))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Low Disk Space Warning')
            && str_contains($request->data()['text'], '/path/to/backups')
            && str_contains($request->data()['text'], '95.5');
    });
});
