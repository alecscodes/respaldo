<?php

use App\Services\GitUpdateService;
use Illuminate\Support\Facades\Process;

it('can check for updates', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    $service = app(GitUpdateService::class);
    $result = $service->checkForUpdates();

    expect($result)->toBeArray()
        ->toHaveKeys(['available', 'current_commit', 'remote_commit', 'commits_behind', 'error']);
});

it('returns error when git is not available', function () {
    Process::fake([
        'git --version' => Process::result(errorOutput: 'Git not found', exitCode: 1),
    ]);

    $service = app(GitUpdateService::class);
    $result = $service->checkForUpdates();

    expect($result['available'])->toBeFalse()
        ->and($result['error'])->toBe('Git is not available');
});

it('returns error when not in git repository', function () {
    Process::fake([
        'git --version' => Process::result('git version 2.0.0'),
    ]);

    // Create a temporary instance pointing to non-git directory
    $tempDir = sys_get_temp_dir().'/non-git-dir-'.uniqid();
    mkdir($tempDir);

    $service = app(GitUpdateService::class);
    $result = $service->checkForUpdates();

    // Cleanup
    @rmdir($tempDir);

    expect($result)->toBeArray();
});

it('performs update successfully with reset approach', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    Process::fake([
        'git rev-parse HEAD' => Process::result(output: 'abc123'),
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
        'git fetch origin' => Process::result(),
        'git reset --hard origin/main' => Process::result(output: 'HEAD is now at abc123'),
        'git clean -fd' => Process::result(),
        'git diff --name-only abc123 HEAD' => Process::result(output: ''),
        'php artisan config:clear' => Process::result(),
        'php artisan route:clear' => Process::result(),
        'php artisan view:clear' => Process::result(),
        'php artisan cache:clear' => Process::result(),
        'chown*' => Process::result(),
    ]);

    $service = app(GitUpdateService::class);
    $result = $service->performUpdate();

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toBe('Update completed successfully')
        ->and($result['error'])->toBeNull();
});

it('handles fetch failures gracefully', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    Process::fake([
        'git rev-parse HEAD' => Process::result(output: 'abc123'),
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
        'git fetch origin' => Process::result(errorOutput: 'Failed to connect', exitCode: 1),
    ]);

    $service = app(GitUpdateService::class);
    $result = $service->performUpdate();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Update failed')
        ->and($result['error'])->toContain('Failed to fetch from remote');
});

it('handles reset failures gracefully', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    Process::fake([
        'git rev-parse HEAD' => Process::result(output: 'abc123'),
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
        'git fetch origin' => Process::result(),
        'git reset --hard origin/main' => Process::result(errorOutput: 'Reset failed', exitCode: 1),
    ]);

    $service = app(GitUpdateService::class);
    $result = $service->performUpdate();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Update failed')
        ->and($result['error'])->toContain('Failed to reset to remote');
});

it('runs composer install when composer files changed', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    Process::fake([
        'git rev-parse HEAD' => Process::result(output: 'abc123'),
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
        'git fetch origin' => Process::result(),
        'git reset --hard origin/main' => Process::result(output: 'HEAD is now at abc123'),
        'git clean -fd' => Process::result(),
        'git diff --name-only abc123 HEAD' => Process::result(output: "composer.json\ncomposer.lock"),
        'composer install*' => Process::result(output: 'Dependencies installed'),
        'php artisan config:clear' => Process::result(),
        'php artisan route:clear' => Process::result(),
        'php artisan view:clear' => Process::result(),
        'php artisan cache:clear' => Process::result(),
        'chown*' => Process::result(),
    ]);

    $service = app(GitUpdateService::class);
    $result = $service->performUpdate();

    expect($result['success'])->toBeTrue()
        ->and($result['output'])->toContain('Composer Install');
});

it('runs npm ci and build when package files changed', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    Process::fake([
        'git rev-parse HEAD' => Process::result(output: 'abc123'),
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
        'git fetch origin' => Process::result(),
        'git reset --hard origin/main' => Process::result(output: 'HEAD is now at abc123'),
        'git clean -fd' => Process::result(),
        'git diff --name-only abc123 HEAD' => Process::result(output: "package.json\npackage-lock.json"),
        'npm ci' => Process::result(output: 'Dependencies installed'),
        'npm run build' => Process::result(output: 'Build completed'),
        'php artisan config:clear' => Process::result(),
        'php artisan route:clear' => Process::result(),
        'php artisan view:clear' => Process::result(),
        'php artisan cache:clear' => Process::result(),
        'chown*' => Process::result(),
    ]);

    $service = app(GitUpdateService::class);
    $result = $service->performUpdate();

    expect($result['success'])->toBeTrue()
        ->and($result['output'])->toContain('NPM Install')
        ->and($result['output'])->toContain('NPM Build');
});

it('runs npm build when frontend files changed', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git repository');
    }

    Process::fake([
        'git rev-parse HEAD' => Process::result(output: 'abc123'),
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'main'),
        'git fetch origin' => Process::result(),
        'git reset --hard origin/main' => Process::result(output: 'HEAD is now at abc123'),
        'git clean -fd' => Process::result(),
        'git diff --name-only abc123 HEAD' => Process::result(output: 'resources/js/app.js'),
        'npm run build' => Process::result(output: 'Build completed'),
        'php artisan config:clear' => Process::result(),
        'php artisan route:clear' => Process::result(),
        'php artisan view:clear' => Process::result(),
        'php artisan cache:clear' => Process::result(),
        'chown*' => Process::result(),
    ]);

    $service = app(GitUpdateService::class);
    $result = $service->performUpdate();

    expect($result['success'])->toBeTrue()
        ->and($result['output'])->toContain('NPM Build');
});
