<?php

use App\Models\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->withoutVite();
});

test('index returns logs page for authenticated user', function () {
    Log::factory()->count(5)->create();

    $response = $this->get('/logs');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 5)
        ->has('categories')
        ->has('levels')
    );
});

test('index filters logs by category', function () {
    Log::factory()->create(['category' => 'backup']);
    Log::factory()->create(['category' => 'api']);
    Log::factory()->create(['category' => 'backup']);

    $response = $this->get('/logs?category=backup');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.category', 'backup')
    );
});

test('index filters logs by level', function () {
    Log::factory()->create(['level' => 'error']);
    Log::factory()->create(['level' => 'info']);
    Log::factory()->create(['level' => 'error']);

    $response = $this->get('/logs?level=error');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.level', 'error')
    );
});

test('index searches logs with contains search', function () {
    Log::factory()->create(['message' => 'Backup created successfully', 'category' => 'backup']);
    Log::factory()->create(['message' => 'Backup failed', 'category' => 'backup']);
    Log::factory()->create(['message' => 'User logged in', 'category' => 'user']);

    $response = $this->get('/logs?search=Backup');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.search', 'Backup')
    );
});

test('index searches logs with regex when use_regex is true', function () {
    Log::factory()->create(['message' => 'Error 404']);
    Log::factory()->create(['message' => 'Error 500']);
    Log::factory()->create(['message' => 'Success 200']);

    $response = $this->get('/logs?search=Error%20[45]&use_regex=1');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->where('filters.search', 'Error [45]')
    );
});

test('index filters logs by date range', function () {
    Log::factory()->create(['created_at' => now()->subDays(5)]);
    Log::factory()->create(['created_at' => now()->subDays(2)]);
    Log::factory()->create(['created_at' => now()]);

    $response = $this->get('/logs?date_from='.now()->subDays(3)->format('Y-m-d').'&date_to='.now()->format('Y-m-d'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
    );
});

test('index filters logs by user', function () {
    $otherUser = User::factory()->create();
    Log::factory()->create(['user_id' => $this->user->id]);
    Log::factory()->create(['user_id' => $otherUser->id]);
    Log::factory()->create(['user_id' => $this->user->id]);

    $response = $this->get('/logs?user_id='.$this->user->id);

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
    );
});

test('index filters logs by app', function () {
    $app1 = \App\Models\App::factory()->create(['user_id' => $this->user->id]);
    $app2 = \App\Models\App::factory()->create(['user_id' => $this->user->id]);

    Log::factory()->create(['context' => ['app_id' => $app1->id]]);
    Log::factory()->create(['context' => ['app_id' => $app2->id]]);
    Log::factory()->create(['context' => ['app_id' => $app1->id]]);

    $response = $this->get('/logs?app_id='.$app1->id);

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.app_id', $app1->id)
    );
});

test('index paginates logs correctly', function () {
    Log::factory()->count(60)->create();

    $response = $this->get('/logs?per_page=20');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 20)
        ->where('logs.per_page', 20)
        ->where('logs.total', 60)
    );
});

test('index returns empty result when no logs match filters', function () {
    Log::factory()->create(['category' => 'backup']);

    $response = $this->get('/logs?category=api');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 0)
    );
});

test('index includes user information in log data', function () {
    $log = Log::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->get('/logs');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 1)
        ->where('logs.data.0.user.id', $this->user->id)
        ->where('logs.data.0.user.name', $this->user->name)
        ->where('logs.data.0.user.email', $this->user->email)
    );
});

test('index validates search parameters', function () {
    $response = $this->get('/logs?level=invalid');

    $response->assertSessionHasErrors('level');
});

test('index validates date parameters', function () {
    $response = $this->get('/logs?date_from=invalid-date');

    $response->assertSessionHasErrors('date_from');
});

test('index validates per_page parameter', function () {
    $response = $this->get('/logs?per_page=200');

    $response->assertSessionHasErrors('per_page');
});

test('destroy deletes all logs when no filters applied', function () {
    Log::factory()->count(5)->create();
    $initialCount = Log::count();

    $response = $this->delete('/logs');

    $response->assertRedirect(route('logs.index'));
    $response->assertSessionHas('success');
    // Should delete all logs, but one new log entry is created for the deletion action
    expect(Log::count())->toBe(1);
    expect(Log::where('category', 'system')->where('message', 'Logs deleted')->count())->toBe(1);
});

test('destroy deletes only filtered logs', function () {
    Log::factory()->create(['category' => 'backup', 'level' => 'info']);
    Log::factory()->create(['category' => 'backup', 'level' => 'error']);
    Log::factory()->create(['category' => 'api', 'level' => 'info']);

    $response = $this->delete('/logs?category=backup&level=info');

    $response->assertRedirect(route('logs.index'));
    // Should delete 1 filtered log, leaving 2 others + 1 deletion log
    expect(Log::count())->toBe(3);
    expect(Log::where('category', 'backup')->where('level', 'info')->count())->toBe(0);
    expect(Log::where('category', 'backup')->where('level', 'error')->count())->toBe(1);
    expect(Log::where('category', 'api')->where('level', 'info')->count())->toBe(1);
});

test('destroy logs deletion action', function () {
    Log::factory()->count(3)->create();

    $this->delete('/logs');

    $deletionLog = Log::where('category', 'system')
        ->where('message', 'Logs deleted')
        ->first();

    expect($deletionLog)->not->toBeNull();
    expect($deletionLog->context['deleted_count'])->toBe(3);
});
