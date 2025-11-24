<?php

use App\Models\App;
use App\Models\Setting;
use App\Models\User;
use App\Services\StorageConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('sends Telegram notification when backup fails due to insufficient storage', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    Storage::fake('backups');

    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test App',
        'storage_size' => 5 * 1024 * 1024, // 5 MB
    ]);

    $this->actingAs($user);

    // Create a file larger than available space (10MB)
    $file = UploadedFile::fake()->create('backup.tar.gz', 10 * 1024); // 10MB file

    $response = $this->post(route('backups.store', $app), [
        'file' => $file,
    ]);

    $response->assertSessionHas('error', 'Not enough storage space available.');

    Http::assertSent(function ($request) use ($app) {
        $data = $request->data();

        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $data['chat_id'] === 'test-chat-id'
            && str_contains($data['text'], 'Insufficient Storage')
            && str_contains($data['text'], $app->name);
    });
});

test('sends Telegram notification when backup fails to store', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    Storage::fake('backups');

    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test App',
        'storage_size' => StorageConverter::gbToBytes(10), // 10 GB
    ]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->create('backup.tar.gz', 100); // Small file

    // Mock DiskSpaceService to simulate disk space check failure
    $diskSpaceServiceMock = $this->mock(\App\Services\DiskSpaceService::class);
    $diskSpaceServiceMock->shouldReceive('getBackupDiskSpace')
        ->times(3) // Called once initially, then after app cleanup, then after all-apps cleanup
        ->andReturn([
            'total' => 100 * 1024 * 1024 * 1024, // 100 GB
            'used' => 99 * 1024 * 1024 * 1024, // 99 GB
            'available' => 50, // Only 50 bytes available (less than file size)
            'percentage_used' => 99.0,
            'path' => '/backups',
        ]);

    $this->app->instance(\App\Services\DiskSpaceService::class, $diskSpaceServiceMock);

    $response = $this->post(route('backups.store', $app), [
        'file' => $file,
    ]);

    $response->assertStatus(500);

    Http::assertSent(function ($request) use ($app) {
        $data = $request->data();

        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $data['chat_id'] === 'test-chat-id'
            && str_contains($data['text'], 'Backup Failed')
            && str_contains($data['text'], $app->name)
            && str_contains($data['text'], 'Insufficient disk space');
    });
});

test('sends Telegram notification when API backup fails due to insufficient storage', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    Storage::fake('backups');

    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $app = App::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test App',
        'storage_size' => 5 * 1024 * 1024, // 5 MB
    ]);

    // Create a file larger than available space (10MB)
    $file = UploadedFile::fake()->create('backup.tar.gz', 10 * 1024); // 10MB file

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/apps/{$app->id}/backups", [
            'file' => $file,
        ]);

    $response->assertStatus(400);
    $response->assertJson(['message' => 'Not enough storage space available.']);

    Http::assertSent(function ($request) use ($app) {
        $data = $request->data();

        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $data['chat_id'] === 'test-chat-id'
            && str_contains($data['text'], 'Insufficient Storage')
            && str_contains($data['text'], $app->name);
    });
});

test('does not send Telegram notification when Telegram is not configured', function () {
    Http::fake();

    Setting::set('telegram_bot_token', null);
    Setting::set('telegram_chat_id', null);

    Storage::fake('backups');

    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'storage_size' => 5 * 1024 * 1024, // 5 MB
    ]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->create('backup.tar.gz', 10 * 1024); // 10MB file

    $response = $this->post(route('backups.store', $app), [
        'file' => $file,
    ]);

    $response->assertSessionHas('error', 'Not enough storage space available.');

    // Should not send any HTTP requests to Telegram
    Http::assertNothingSent();
});

test('sends disk space warning when disk usage exceeds 90%', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    Storage::fake('backups');

    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'storage_size' => StorageConverter::gbToBytes(10), // 10 GB
    ]);

    $this->actingAs($user);

    // Mock DiskSpaceService to return high disk usage
    $this->mock(\App\Services\DiskSpaceService::class, function ($mock) {
        $mock->shouldReceive('getBackupDiskSpace')
            ->atLeast()->once() // May be called multiple times if retention cleanup runs
            ->andReturn([
                'total' => 100 * 1024 * 1024 * 1024, // 100 GB
                'used' => 95 * 1024 * 1024 * 1024, // 95 GB
                'available' => 5 * 1024 * 1024 * 1024, // 5 GB
                'percentage_used' => 95.0,
                'path' => '/backups',
            ]);
    });

    $file = UploadedFile::fake()->create('backup.tar.gz', 100); // Small file

    $response = $this->post(route('backups.store', $app), [
        'file' => $file,
    ]);

    $response->assertRedirect();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $data['chat_id'] === 'test-chat-id'
            && str_contains($data['text'], 'Low Disk Space Warning')
            && str_contains($data['text'], '95')
            && str_contains($data['text'], '/backups');
    });
});
