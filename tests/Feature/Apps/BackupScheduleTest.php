<?php

use App\Models\App;
use App\Models\User;

test('can create app with daily backup schedule', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('apps.store'), [
        'name' => 'Daily Backup App',
        'storage_size' => 10.5,
        'backup_period' => 'daily',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('apps', [
        'name' => 'Daily Backup App',
        'user_id' => $user->id,
        'backup_period' => 'daily',
        'backup_days' => null,
    ]);
});

test('can create app with weekly backup schedule and days', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('apps.store'), [
        'name' => 'Weekly Backup App',
        'storage_size' => 20,
        'backup_period' => 'weekly',
        'backup_days' => ['M', 'W', 'F'],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('apps', [
        'name' => 'Weekly Backup App',
        'user_id' => $user->id,
        'backup_period' => 'weekly',
    ]);

    $app = App::where('name', 'Weekly Backup App')->first();
    expect($app->backup_days)->toBe(['M', 'W', 'F']);
});

test('can create app with monthly backup schedule', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('apps.store'), [
        'name' => 'Monthly Backup App',
        'storage_size' => 15,
        'backup_period' => 'monthly',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('apps', [
        'name' => 'Monthly Backup App',
        'user_id' => $user->id,
        'backup_period' => 'monthly',
        'backup_days' => null,
    ]);
});

test('can update app backup schedule', function () {
    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'backup_period' => 'daily',
        'backup_days' => null,
    ]);
    $this->actingAs($user);

    $response = $this->put(route('apps.update', $app), [
        'backup_period' => 'weekly',
        'backup_days' => ['T', 'R'],
    ]);

    $response->assertRedirect();
    $app->refresh();
    expect($app->backup_period)->toBe('weekly');
    expect($app->backup_days)->toBe(['T', 'R']);
});

test('backup days are set to null when period is not weekly', function () {
    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'backup_period' => 'weekly',
        'backup_days' => ['M', 'W', 'F'],
    ]);
    $this->actingAs($user);

    $response = $this->put(route('apps.update', $app), [
        'backup_period' => 'daily',
    ]);

    $response->assertRedirect();
    $app->refresh();
    expect($app->backup_period)->toBe('daily');
    expect($app->backup_days)->toBeNull();
});

test('validates backup days are required when period is weekly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('apps.store'), [
        'name' => 'Invalid App',
        'storage_size' => 10,
        'backup_period' => 'weekly',
        'backup_days' => [],
    ]);

    $response->assertSessionHasErrors('backup_days');
});

test('validates backup days contain valid day codes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('apps.store'), [
        'name' => 'Invalid App',
        'storage_size' => 10,
        'backup_period' => 'weekly',
        'backup_days' => ['X', 'Y', 'Z'],
    ]);

    $response->assertSessionHasErrors('backup_days.0');
});

test('validates backup period is one of allowed values', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('apps.store'), [
        'name' => 'Invalid App',
        'storage_size' => 10,
        'backup_period' => 'invalid',
    ]);

    $response->assertSessionHasErrors('backup_period');
});

test('can remove backup schedule from app', function () {
    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'backup_period' => 'daily',
        'backup_days' => null,
    ]);
    $this->actingAs($user);

    $response = $this->put(route('apps.update', $app), [
        'backup_period' => null,
    ]);

    $response->assertRedirect();
    $app->refresh();
    expect($app->backup_period)->toBeNull();
    expect($app->backup_days)->toBeNull();
});
