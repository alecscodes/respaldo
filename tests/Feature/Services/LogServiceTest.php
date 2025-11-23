<?php

use App\Models\Log;
use App\Models\User;
use App\Services\LogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new LogService;
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('log method creates log entry via database channel', function () {
    $this->service->log('info', 'test', 'Test message', ['key' => 'value']);

    $log = Log::where('message', 'Test message')->first();

    expect($log)->not->toBeNull();
    expect($log->level)->toBe('info');
    expect($log->category)->toBe('test');
    expect($log->message)->toBe('Test message');
    expect($log->context)->toBe(['key' => 'value']);
    expect($log->user_id)->toBe($this->user->id);
});

test('all log level methods work', function (string $level) {
    $this->service->{$level}('test', 'Test message');

    $log = Log::where('message', 'Test message')->first();

    expect($log->level)->toBe($level);
    expect($log->category)->toBe('test');
})->with(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']);

test('log works without context', function () {
    $this->service->info('test', 'Test message');

    $log = Log::where('message', 'Test message')->first();

    expect($log->context)->toBeNull();
});

test('log captures user when authenticated', function () {
    $this->service->info('test', 'Test message');

    $log = Log::where('message', 'Test message')->first();

    expect($log->user_id)->toBe($this->user->id);
});

test('log works when user not authenticated', function () {
    auth()->logout();

    $this->service->info('test', 'Test message');

    $log = Log::where('message', 'Test message')->first();

    expect($log->user_id)->toBeNull();
});
