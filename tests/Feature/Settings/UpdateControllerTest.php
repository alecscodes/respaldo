<?php

use App\Services\GitUpdateService;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->user = \App\Models\User::factory()->create();
});

it('requires authentication to check for updates', function () {
    $response = $this->get(route('updates.check'));

    $response->assertRedirect(route('login'));
});

it('requires authentication to perform update', function () {
    $response = $this->post(route('updates.update'));

    $response->assertRedirect(route('login'));
});

it('can check for updates when authenticated', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    $response = $this->actingAs($this->user)
        ->getJson(route('updates.check'));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'available',
            'current_commit',
            'remote_commit',
            'commits_behind',
            'error',
        ]);
});

it('returns error when git is not available', function () {
    Process::fake([
        'git --version' => Process::result(errorOutput: 'Git not found', exitCode: 1),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('updates.check'));

    $response->assertSuccessful()
        ->assertJson([
            'available' => false,
            'error' => 'Git is not available',
        ]);
});

it('can perform update when authenticated', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    // Mock isRunningInDocker to return true to test Docker path
    $service = $this->createPartialMock(GitUpdateService::class, ['isRunningInDocker']);
    $service->method('isRunningInDocker')->willReturn(true);
    $this->app->instance(GitUpdateService::class, $service);

    Process::fake([
        'git rev-parse HEAD' => Process::result(output: 'abc123'),
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
        'git fetch origin' => Process::result(),
        'git reset --hard origin/main' => Process::result(output: 'HEAD is now at abc123'),
        'git clean -fd' => Process::result(),
        'git diff --name-only abc123 HEAD' => Process::result(output: ''),
        'composer install*' => Process::result(output: 'Dependencies installed'),
        'npm ci' => Process::result(output: 'Dependencies installed'),
        'npm run build' => Process::result(output: 'Build completed'),
        'php artisan migrate --force' => Process::result(output: 'Migrations completed'),
        'php artisan config:clear' => Process::result(),
        'php artisan route:clear' => Process::result(),
        'php artisan view:clear' => Process::result(),
        'php artisan cache:clear' => Process::result(),
        'php artisan optimize' => Process::result(output: 'Optimized'),
        'composer dump-autoload*' => Process::result(output: 'Autoload dumped'),
        'chown*' => Process::result(),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('updates.update'));

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Update completed successfully',
        ]);
});

it('handles update failures gracefully', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    // Mock isRunningInDocker to return true to test Docker path
    $service = $this->createPartialMock(GitUpdateService::class, ['isRunningInDocker']);
    $service->method('isRunningInDocker')->willReturn(true);
    $this->app->instance(GitUpdateService::class, $service);

    Process::fake([
        'git rev-parse HEAD' => Process::result(output: 'abc123'),
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
        'git fetch origin' => Process::result(errorOutput: 'Failed to connect', exitCode: 1),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('updates.update'));

    $response->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'Update failed',
        ])
        ->assertJsonStructure(['error']);
});

it('handles exceptions during update', function () {
    $mockService = $this->mock(GitUpdateService::class);
    $mockService->shouldReceive('performUpdate')
        ->andThrow(new \Exception('Test exception'));

    $this->app->instance(GitUpdateService::class, $mockService);

    $response = $this->actingAs($this->user)
        ->postJson(route('updates.update'));

    $response->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'Update failed',
            'error' => 'Test exception',
        ]);
});
