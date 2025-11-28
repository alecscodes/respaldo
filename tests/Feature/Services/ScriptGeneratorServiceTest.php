<?php

use App\Models\User;
use App\Services\ScriptGeneratorService;

test('generates script with token and base url', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $baseUrl = 'https://example.com';

    $script = $service->generateScript($user, $baseUrl);

    expect($script)->toContain("BASE_URL=\"$baseUrl\"");
    expect($script)->toContain('TOKEN=');
    expect($script)->toContain('#!/bin/sh');
});

test('generated script contains chunked upload implementation', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $script = $service->generateScript($user, 'https://example.com');

    // Check for chunked upload initialization
    expect($script)->toContain('backups/chunked/init');
    expect($script)->toContain('chunk_size=$((10 * 1024 * 1024))');

    // Check for chunk upload
    expect($script)->toContain('backups/chunked/upload');
    expect($script)->toContain('chunk_index');
    expect($script)->toContain('upload_id');

    // Check for finalization
    expect($script)->toContain('backups/chunked/finalize');

    // Check for progress display
    expect($script)->toContain('Progress:');
    expect($script)->toContain('chunk');
});

test('generated script uses API endpoints', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $script = $service->generateScript($user, 'https://example.com');

    // Should use /api/ endpoints
    expect($script)->toContain('/api/apps/');
    expect($script)->toContain('/api/apps/$app_id/backups/chunked/init');
    expect($script)->toContain('/api/apps/$app_id/backups/chunked/upload');
    expect($script)->toContain('/api/apps/$app_id/backups/chunked/finalize');
});

test('generated script includes error handling', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $script = $service->generateScript($user, 'https://example.com');

    // Check for error handling in chunked upload
    expect($script)->toContain('Error initializing upload');
    expect($script)->toContain('Error: Failed to upload');
    expect($script)->toContain('Error finalizing upload');
});

test('generated script includes chunk extraction logic', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $script = $service->generateScript($user, 'https://example.com');

    // Check for dd command to extract chunks
    expect($script)->toContain('dd if=');
    expect($script)->toContain('bs=');
    expect($script)->toContain('skip=');
    expect($script)->toContain('count=');
    expect($script)->toContain('length=');
});

test('generated script includes progress calculation', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $script = $service->generateScript($user, 'https://example.com');

    // Check for progress calculation
    expect($script)->toContain('progress=$((uploaded_count * 100 / total_chunks))');
    expect($script)->toContain('Progress:');
});

test('generated script handles chunk size calculation correctly', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $script = $service->generateScript($user, 'https://example.com');

    // Check for proper chunk length calculation (handles last chunk)
    expect($script)->toContain('length=$((start + chunk_size > file_size ? file_size - start : chunk_size))');
});

test('generated script includes cleanup logic', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $script = $service->generateScript($user, 'https://example.com');

    // Check for cleanup of temporary files
    expect($script)->toContain('rm -f "$chunk_temp"');
    expect($script)->toContain('rm -f "$backup_file" "$exclude_file"');
});

test('generated script re-executes with original arguments after update', function () {
    $user = User::factory()->create();
    $service = new ScriptGeneratorService;
    $script = $service->generateScript($user, 'https://example.com');

    expect($script)->toContain('auto_update_script "$@"');
});
