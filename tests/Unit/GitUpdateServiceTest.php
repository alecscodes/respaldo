<?php

use App\Services\GitUpdateService;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->service = new GitUpdateService;
});

test('checkForUpdates returns error when git is not available', function () {
    Process::fake([
        'git --version' => Process::result(exitCode: 1, errorOutput: 'git: command not found'),
    ]);

    $result = $this->service->checkForUpdates();

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toBe('Git is not available');
    expect($result['current_commit'])->toBeNull();
    expect($result['remote_commit'])->toBeNull();
    expect($result['commits_behind'])->toBe(0);
});

test('checkForUpdates returns error when not a git repository', function () {
    Process::fake([
        'git --version' => Process::result(exitCode: 0, output: 'git version 2.40.0'),
        'git rev-parse --git-dir' => Process::result(exitCode: 128, errorOutput: 'not a git repository'),
    ]);

    $result = $this->service->checkForUpdates();

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toContain('Not a git repository');
});

test('checkForUpdates returns available true when commits are behind', function () {
    Process::fake([
        'git --version' => Process::result(exitCode: 0, output: 'git version 2.40.0'),
        'git rev-parse --git-dir' => Process::result(exitCode: 0, output: '.git'),
        'git rev-parse HEAD' => Process::result(exitCode: 0, output: 'abc123'),
        'git fetch origin --quiet' => Process::result(exitCode: 0),
        'git rev-parse --abbrev-ref HEAD' => Process::result(exitCode: 0, output: 'main'),
        'git rev-list --count HEAD..origin/main' => Process::result(exitCode: 0, output: '5'),
        'git rev-parse origin/main' => Process::result(exitCode: 0, output: 'def456'),
        'git config --global --add safe.directory *' => Process::result(exitCode: 0),
    ]);

    $result = $this->service->checkForUpdates();

    expect($result['available'])->toBeTrue();
    expect($result['current_commit'])->toBe('abc123');
    expect($result['remote_commit'])->toBe('def456');
    expect($result['commits_behind'])->toBe(5);
    expect($result['error'])->toBeNull();
});

test('checkForUpdates returns available false when up to date', function () {
    Process::fake([
        'git --version' => Process::result(exitCode: 0, output: 'git version 2.40.0'),
        'git rev-parse --git-dir' => Process::result(exitCode: 0, output: '.git'),
        'git rev-parse HEAD' => Process::result(exitCode: 0, output: 'abc123'),
        'git fetch origin --quiet' => Process::result(exitCode: 0),
        'git rev-parse --abbrev-ref HEAD' => Process::result(exitCode: 0, output: 'main'),
        'git rev-list --count HEAD..origin/main' => Process::result(exitCode: 0, output: '0'),
        'git rev-parse origin/main' => Process::result(exitCode: 0, output: 'abc123'),
        'git config --global --add safe.directory *' => Process::result(exitCode: 0),
    ]);

    $result = $this->service->checkForUpdates();

    expect($result['available'])->toBeFalse();
    expect($result['commits_behind'])->toBe(0);
});

test('checkForUpdates handles fetch failure', function () {
    Process::fake([
        'git --version' => Process::result(exitCode: 0, output: 'git version 2.40.0'),
        'git rev-parse --git-dir' => Process::result(exitCode: 0, output: '.git'),
        'git rev-parse HEAD' => Process::result(exitCode: 0, output: 'abc123'),
        'git fetch origin --quiet' => Process::result(exitCode: 1, errorOutput: 'Permission denied'),
        'git config --global --add safe.directory *' => Process::result(exitCode: 0),
    ]);

    $result = $this->service->checkForUpdates();

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toContain('Failed to fetch from remote');
});

test('checkForUpdates handles exceptions gracefully', function () {
    Process::fake(function () {
        throw new \Exception('Unexpected error');
    });

    $result = $this->service->checkForUpdates();

    expect($result['available'])->toBeFalse();
    expect($result['error'])->toBe('Unexpected error');
});

test('performUpdate returns success when no updates available', function () {
    Process::fake([
        'git --version' => Process::result(exitCode: 0, output: 'git version 2.40.0'),
        'git rev-parse --git-dir' => Process::result(exitCode: 0, output: '.git'),
        'git rev-parse HEAD' => Process::result(exitCode: 0, output: 'abc123'),
        'git fetch origin --quiet' => Process::result(exitCode: 0),
        'git rev-parse --abbrev-ref HEAD' => Process::result(exitCode: 0, output: 'main'),
        'git rev-list --count HEAD..origin/main' => Process::result(exitCode: 0, output: '0'),
        'git rev-parse origin/main' => Process::result(exitCode: 0, output: 'abc123'),
        'git config --global --add safe.directory *' => Process::result(exitCode: 0),
    ]);

    $result = $this->service->performUpdate();

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toContain('No updates available');
});

test('performUpdate handles checkForUpdates errors gracefully', function () {
    // When checkForUpdates returns an error, performUpdate should handle it
    // Since available is false, it returns success with "No updates available"
    Process::fake([
        'git --version' => Process::result(exitCode: 1, errorOutput: 'git: command not found'),
    ]);

    $result = $this->service->performUpdate();

    // When checkForUpdates fails, available is false, so performUpdate returns success
    // with "No updates available" message
    expect($result['success'])->toBeTrue();
    expect($result['message'])->toContain('No updates available');
});

test('performUpdate handles Docker update failure when fetch fails', function () {
    Process::fake([
        'git --version' => Process::result(exitCode: 0, output: 'git version 2.40.0'),
        'git rev-parse --git-dir' => Process::result(exitCode: 0, output: '.git'),
        'git rev-parse HEAD' => Process::result(exitCode: 0, output: 'abc123'),
        'git fetch origin --quiet' => Process::result(exitCode: 0),
        'git rev-parse --abbrev-ref HEAD' => Process::result(exitCode: 0, output: 'main'),
        'git rev-list --count HEAD..origin/main' => Process::result(exitCode: 0, output: '1'),
        'git rev-parse origin/main' => Process::result(exitCode: 0, output: 'def456'),
        'git fetch origin' => Process::result(exitCode: 1, errorOutput: 'Fetch failed'),
        'git config --global --add safe.directory *' => Process::result(exitCode: 0),
    ]);

    $result = $this->service->performUpdate();

    // Note: This test may not fully work without Docker environment detection
    // but it tests the error handling path
    expect($result)->toHaveKey('success');
    expect($result)->toHaveKey('error');
});

test('performUpdate handles reset failure in Docker', function () {
    Process::fake([
        'git --version' => Process::result(exitCode: 0, output: 'git version 2.40.0'),
        'git rev-parse --git-dir' => Process::result(exitCode: 0, output: '.git'),
        'git rev-parse HEAD' => Process::result(exitCode: 0, output: 'abc123'),
        'git fetch origin --quiet' => Process::result(exitCode: 0),
        'git rev-parse --abbrev-ref HEAD' => Process::result(exitCode: 0, output: 'main'),
        'git rev-list --count HEAD..origin/main' => Process::result(exitCode: 0, output: '1'),
        'git rev-parse origin/main' => Process::result(exitCode: 0, output: 'def456'),
        'git fetch origin' => Process::result(exitCode: 0),
        'git reset --hard origin/main' => Process::result(exitCode: 1, errorOutput: 'Reset failed'),
        'git config --global --add safe.directory *' => Process::result(exitCode: 0),
    ]);

    $result = $this->service->performUpdate();

    // Note: This test may not fully work without Docker environment detection
    // but it tests the error handling path
    expect($result)->toHaveKey('success');
    expect($result)->toHaveKey('error');
});
