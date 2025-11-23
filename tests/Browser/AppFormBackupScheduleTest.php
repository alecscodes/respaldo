<?php

use App\Models\App;
use App\Models\User;

test('can create app with daily backup schedule', function () {
    $user = User::factory()->create();
    actingAs($user);

    $page = visit(route('apps.index'));

    $page->click('Create App')
        ->waitFor('form')
        ->fill('name', 'Test App')
        ->fill('storage_size', '10.5')
        ->select('backup_period', 'daily')
        ->click('Create App')
        ->waitForUrl(route('apps.show', App::where('name', 'Test App')->first(), absolute: false));

    $app = App::where('name', 'Test App')->first();
    expect($app)->not->toBeNull();
    expect($app->backup_period)->toBe('daily');
    expect($app->backup_days)->toBeNull();
});

test('can create app with weekly backup schedule and specific days', function () {
    $user = User::factory()->create();
    actingAs($user);

    $page = visit(route('apps.index'));

    $page->click('Create App')
        ->waitFor('form')
        ->fill('name', 'Weekly App')
        ->fill('storage_size', '20')
        ->select('backup_period', 'weekly')
        ->waitFor('[type="checkbox"]')
        ->check('M')
        ->check('W')
        ->check('F')
        ->click('Create App')
        ->waitForUrl(route('apps.show', App::where('name', 'Weekly App')->first(), absolute: false));

    $app = App::where('name', 'Weekly App')->first();
    expect($app)->not->toBeNull();
    expect($app->backup_period)->toBe('weekly');
    expect($app->backup_days)->toBe(['M', 'W', 'F']);
});

test('can create app with monthly backup schedule', function () {
    $user = User::factory()->create();
    actingAs($user);

    $page = visit(route('apps.index'));

    $page->click('Create App')
        ->waitFor('form')
        ->fill('name', 'Monthly App')
        ->fill('storage_size', '15')
        ->select('backup_period', 'monthly')
        ->click('Create App')
        ->waitForUrl(route('apps.show', App::where('name', 'Monthly App')->first(), absolute: false));

    $app = App::where('name', 'Monthly App')->first();
    expect($app)->not->toBeNull();
    expect($app->backup_period)->toBe('monthly');
    expect($app->backup_days)->toBeNull();
});

test('can update app backup schedule from daily to weekly', function () {
    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'backup_period' => 'daily',
        'backup_days' => null,
    ]);
    actingAs($user);

    $page = visit(route('apps.show', $app));

    $page->click('Edit App')
        ->waitFor('select[id="backup_period"]')
        ->select('backup_period', 'weekly')
        ->waitFor('[type="checkbox"]')
        ->check('T')
        ->check('R')
        ->click('Update App')
        ->waitForUrl(route('apps.show', $app, absolute: false));

    $app->refresh();
    expect($app->backup_period)->toBe('weekly');
    expect($app->backup_days)->toBe(['T', 'R']);
});

test('backup days are cleared when changing from weekly to daily', function () {
    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'backup_period' => 'weekly',
        'backup_days' => ['M', 'W', 'F'],
    ]);
    actingAs($user);

    $page = visit(route('apps.show', $app));

    $page->click('Edit App')
        ->waitFor('select[id="backup_period"]')
        ->select('backup_period', 'daily')
        ->wait(500) // Wait for UI update
        ->click('Update App')
        ->waitForUrl(route('apps.show', $app, absolute: false));

    $app->refresh();
    expect($app->backup_period)->toBe('daily');
    expect($app->backup_days)->toBeNull();
});

test('backup days section only shows when weekly is selected', function () {
    $user = User::factory()->create();
    actingAs($user);

    $page = visit(route('apps.index'));

    $page->click('Create App')
        ->waitFor('form')
        ->assertDontSee('Backup Days (Weekly)')
        ->select('backup_period', 'weekly')
        ->waitFor('Backup Days (Weekly)')
        ->assertSee('Backup Days (Weekly)')
        ->assertSee('Monday')
        ->assertSee('Tuesday')
        ->assertSee('Wednesday')
        ->assertSee('Thursday')
        ->assertSee('Friday')
        ->assertSee('Saturday')
        ->assertSee('Sunday')
        ->select('backup_period', 'daily')
        ->wait(500)
        ->assertDontSee('Backup Days (Weekly)');
});

test('can toggle multiple backup days for weekly schedule', function () {
    $user = User::factory()->create();
    actingAs($user);

    $page = visit(route('apps.index'));

    $page->click('Create App')
        ->waitFor('form')
        ->fill('name', 'Multi Day App')
        ->fill('storage_size', '10')
        ->select('backup_period', 'weekly')
        ->waitFor('[type="checkbox"]')
        ->check('M')
        ->check('T')
        ->check('W')
        ->uncheck('T')
        ->click('Create App')
        ->waitForUrl(route('apps.show', App::where('name', 'Multi Day App')->first(), absolute: false));

    $app = App::where('name', 'Multi Day App')->first();
    expect($app->backup_days)->toBe(['M', 'W']);
});

test('validation error shows when weekly schedule has no days selected', function () {
    $user = User::factory()->create();
    actingAs($user);

    $page = visit(route('apps.index'));

    $page->click('Create App')
        ->waitFor('form')
        ->fill('name', 'Invalid App')
        ->fill('storage_size', '10')
        ->select('backup_period', 'weekly')
        ->waitFor('[type="checkbox"]')
        ->click('Create App')
        ->waitFor('The backup days field is required when backup period is weekly')
        ->assertSee('The backup days field is required when backup period is weekly');
});
