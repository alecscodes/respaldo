<?php

use App\Models\App;
use App\Models\Backup;
use App\Models\ChunkUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('chunks');
    Storage::fake('backups');
});

test('guests cannot initialize chunk upload', function () {
    $app = App::factory()->create();

    $response = $this->postJson(route('backups.chunked.init', $app), [
        'filename' => 'test.tar.gz',
        'total_size' => 100 * 1024 * 1024,
        'chunk_size' => 10 * 1024 * 1024,
    ]);

    $response->assertUnauthorized();
});

test('users can initialize chunk upload for their app', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->postJson(route('backups.chunked.init', $app), [
        'filename' => 'test.tar.gz',
        'total_size' => 100 * 1024 * 1024,
        'chunk_size' => 10 * 1024 * 1024,
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'upload_id',
        'total_chunks',
        'chunk_size',
    ]);

    $this->assertDatabaseHas('chunk_uploads', [
        'app_id' => $app->id,
        'user_id' => $user->id,
        'filename' => 'test.tar.gz',
        'total_size' => 100 * 1024 * 1024,
        'total_chunks' => 10,
        'chunk_size' => 10 * 1024 * 1024,
        'status' => 'in_progress',
    ]);
});

test('users cannot initialize chunk upload for other users app', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $app = App::factory()->create(['user_id' => $otherUser->id]);
    $this->actingAs($user);

    $response = $this->postJson(route('backups.chunked.init', $app), [
        'filename' => 'test.tar.gz',
        'total_size' => 100 * 1024 * 1024,
        'chunk_size' => 10 * 1024 * 1024,
    ]);

    $response->assertForbidden();
});

test('chunk upload initialization requires valid data', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->postJson(route('backups.chunked.init', $app), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['filename', 'total_size', 'chunk_size']);
});

test('chunk upload initialization validates file size limits', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    // Test max file size (2TB)
    $response = $this->postJson(route('backups.chunked.init', $app), [
        'filename' => 'test.tar.gz',
        'total_size' => 3 * 1024 * 1024 * 1024 * 1024, // 3TB
        'chunk_size' => 10 * 1024 * 1024,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['total_size']);

    // Test chunk size limits
    $response = $this->postJson(route('backups.chunked.init', $app), [
        'filename' => 'test.tar.gz',
        'total_size' => 100 * 1024 * 1024,
        'chunk_size' => 200 * 1024 * 1024, // 200MB (exceeds max)
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['chunk_size']);
});

test('chunk upload initialization fails if insufficient storage space', function () {
    $user = User::factory()->create();
    // Create app with very small storage (5MB)
    $app = App::factory()->create([
        'user_id' => $user->id,
        'storage_size' => 5 * 1024 * 1024, // 5 MB
    ]);
    $this->actingAs($user);

    // Try to initialize upload for a file larger than available space (10MB)
    $response = $this->postJson(route('backups.chunked.init', $app), [
        'filename' => 'test.tar.gz',
        'total_size' => 10 * 1024 * 1024, // 10MB
        'chunk_size' => 5 * 1024 * 1024, // 5MB chunks
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Not enough storage space available.']);
});

test('chunk upload initialization succeeds if sufficient storage space', function () {
    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'storage_size' => 100 * 1024 * 1024, // 100 MB
    ]);
    $this->actingAs($user);

    $response = $this->postJson(route('backups.chunked.init', $app), [
        'filename' => 'test.tar.gz',
        'total_size' => 50 * 1024 * 1024, // 50MB
        'chunk_size' => 10 * 1024 * 1024, // 10MB chunks
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('chunk_uploads', [
        'app_id' => $app->id,
        'user_id' => $user->id,
        'filename' => 'test.tar.gz',
        'total_size' => 50 * 1024 * 1024,
    ]);
});

test('users can upload chunks', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkSize = 10 * 1024 * 1024;
    $totalSize = $chunkSize * 3;
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'total_chunks' => 3,
        'chunk_size' => $chunkSize,
        'total_size' => $totalSize,
    ]);
    $this->actingAs($user);

    // UploadedFile::fake()->create() size is in KB, not bytes
    $chunk = UploadedFile::fake()->create('chunk', $chunkSize / 1024);

    $response = $this->postJson(route('backups.chunked.upload', $app), [
        'upload_id' => $chunkUpload->upload_id,
        'chunk_index' => 0,
        'chunk' => $chunk,
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'message',
        'progress',
        'uploaded_chunks',
        'total_chunks',
    ]);

    $chunkUpload->refresh();
    expect($chunkUpload->hasChunk(0))->toBeTrue();
    expect($chunkUpload->uploaded_chunks)->toContain(0);
});

test('users cannot upload chunks for other users uploads', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $app = App::factory()->create(['user_id' => $otherUser->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $otherUser->id,
    ]);
    $this->actingAs($user);

    $chunk = UploadedFile::fake()->create('chunk', 10 * 1024 * 1024);

    $response = $this->postJson(route('backups.chunked.upload', $app), [
        'upload_id' => $chunkUpload->upload_id,
        'chunk_index' => 0,
        'chunk' => $chunk,
    ]);

    $response->assertForbidden();
});

test('chunk upload validates chunk size', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'total_chunks' => 1,
        'chunk_size' => 10 * 1024 * 1024,
        'total_size' => 10 * 1024 * 1024,
    ]);
    $this->actingAs($user);

    // Wrong chunk size (UploadedFile::fake()->create() size is in KB)
    $chunk = UploadedFile::fake()->create('chunk', 5 * 1024);

    $response = $this->postJson(route('backups.chunked.upload', $app), [
        'upload_id' => $chunkUpload->upload_id,
        'chunk_index' => 0,
        'chunk' => $chunk,
    ]);

    $response->assertStatus(500);
    $response->assertJson(['error' => true]);
});

test('already uploaded chunks are handled gracefully', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'uploaded_chunks' => [0],
    ]);
    $this->actingAs($user);

    $chunk = UploadedFile::fake()->create('chunk', 10 * 1024 * 1024);

    $response = $this->postJson(route('backups.chunked.upload', $app), [
        'upload_id' => $chunkUpload->upload_id,
        'chunk_index' => 0,
        'chunk' => $chunk,
    ]);

    $response->assertSuccessful();
    $response->assertJson(['message' => 'Chunk already uploaded.']);
});

test('users can finalize chunk upload when all chunks are uploaded', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'total_chunks' => 2,
        'chunk_size' => 10 * 1024 * 1024,
        'total_size' => 20 * 1024 * 1024,
        'uploaded_chunks' => [0, 1],
    ]);
    $this->actingAs($user);

    // Create chunk files
    Storage::disk('chunks')->put("{$chunkUpload->upload_id}/chunk_0", str_repeat('a', 10 * 1024 * 1024));
    Storage::disk('chunks')->put("{$chunkUpload->upload_id}/chunk_1", str_repeat('b', 10 * 1024 * 1024));

    $response = $this->postJson(route('backups.chunked.finalize', $app), [
        'upload_id' => $chunkUpload->upload_id,
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'message',
        'backup_id',
    ]);

    $this->assertDatabaseHas('backups', [
        'app_id' => $app->id,
        'user_id' => $user->id,
        'size' => 20 * 1024 * 1024,
    ]);

    $chunkUpload->refresh();
    expect($chunkUpload->status)->toBe('completed');
    expect($chunkUpload->file_path)->not->toBeNull();

    // Verify chunks were cleaned up
    expect(Storage::disk('chunks')->exists($chunkUpload->upload_id))->toBeFalse();
});

test('finalization fails if chunks are missing', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'total_chunks' => 3,
        'uploaded_chunks' => [0, 1], // Missing chunk 2
    ]);
    $this->actingAs($user);

    $response = $this->postJson(route('backups.chunked.finalize', $app), [
        'upload_id' => $chunkUpload->upload_id,
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Not all chunks have been uploaded.']);
});

test('users can check chunk upload status', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'uploaded_chunks' => [0, 1],
        'total_chunks' => 5,
    ]);
    $this->actingAs($user);

    $response = $this->getJson(route('backups.chunked.status', [$app, $chunkUpload->upload_id]));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'upload_id',
        'status',
        'progress',
        'uploaded_chunks',
        'total_chunks',
        'missing_chunks',
        'error_message',
    ]);

    $response->assertJson([
        'status' => 'in_progress',
        'uploaded_chunks' => 2,
        'total_chunks' => 5,
    ]);
});

test('users cannot check status of other users uploads', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $app = App::factory()->create(['user_id' => $otherUser->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $otherUser->id,
    ]);
    $this->actingAs($user);

    $response = $this->getJson(route('backups.chunked.status', [$app, $chunkUpload->upload_id]));

    $response->assertForbidden();
});

test('finalization creates backup with correct file', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'total_chunks' => 2,
        'chunk_size' => 10 * 1024 * 1024,
        'total_size' => 20 * 1024 * 1024,
        'uploaded_chunks' => [0, 1],
    ]);
    $this->actingAs($user);

    // Create chunk files with different content
    $chunk1Content = str_repeat('A', 10 * 1024 * 1024);
    $chunk2Content = str_repeat('B', 10 * 1024 * 1024);
    Storage::disk('chunks')->put("{$chunkUpload->upload_id}/chunk_0", $chunk1Content);
    Storage::disk('chunks')->put("{$chunkUpload->upload_id}/chunk_1", $chunk2Content);

    $response = $this->postJson(route('backups.chunked.finalize', $app), [
        'upload_id' => $chunkUpload->upload_id,
    ]);

    $response->assertCreated();

    $backup = Backup::where('app_id', $app->id)->first();
    expect($backup)->not->toBeNull();
    expect(Storage::disk('backups')->exists($backup->file_path))->toBeTrue();
    expect(Storage::disk('backups')->size($backup->file_path))->toBe(20 * 1024 * 1024);

    // Verify file content is correct
    $finalContent = Storage::disk('backups')->get($backup->file_path);
    expect(substr($finalContent, 0, 10 * 1024 * 1024))->toBe($chunk1Content);
    expect(substr($finalContent, 10 * 1024 * 1024))->toBe($chunk2Content);
});

test('finalization fails if file size mismatch', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'total_chunks' => 2,
        'chunk_size' => 10 * 1024 * 1024,
        'total_size' => 20 * 1024 * 1024,
        'uploaded_chunks' => [0, 1],
    ]);
    $this->actingAs($user);

    // Create chunk files with wrong size
    Storage::disk('chunks')->put("{$chunkUpload->upload_id}/chunk_0", str_repeat('a', 5 * 1024 * 1024));
    Storage::disk('chunks')->put("{$chunkUpload->upload_id}/chunk_1", str_repeat('b', 5 * 1024 * 1024));

    $response = $this->postJson(route('backups.chunked.finalize', $app), [
        'upload_id' => $chunkUpload->upload_id,
    ]);

    $response->assertStatus(500);
    $response->assertJsonStructure(['error']);

    $chunkUpload->refresh();
    expect($chunkUpload->status)->toBe('failed');
});

test('chunk upload handles last chunk correctly when smaller', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkSize = 10 * 1024 * 1024;
    $totalSize = 25 * 1024 * 1024; // 25MB, so last chunk is 5MB
    $chunkUpload = ChunkUpload::factory()->inProgress()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'total_chunks' => 3,
        'chunk_size' => $chunkSize,
        'total_size' => $totalSize,
        'uploaded_chunks' => [0, 1],
    ]);
    $this->actingAs($user);

    // Upload last chunk (smaller) - UploadedFile::fake()->create() size is in KB
    $lastChunk = UploadedFile::fake()->create('chunk', 5 * 1024);

    $response = $this->postJson(route('backups.chunked.upload', $app), [
        'upload_id' => $chunkUpload->upload_id,
        'chunk_index' => 2,
        'chunk' => $lastChunk,
    ]);

    $response->assertSuccessful();

    $chunkUpload->refresh();
    expect($chunkUpload->hasChunk(2))->toBeTrue();
});

test('cannot finalize upload that is not in progress', function () {
    $user = User::factory()->create();
    $app = App::factory()->create(['user_id' => $user->id]);
    $chunkUpload = ChunkUpload::factory()->completed()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
    ]);
    $this->actingAs($user);

    $response = $this->postJson(route('backups.chunked.finalize', $app), [
        'upload_id' => $chunkUpload->upload_id,
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Upload session is not in progress.']);
});
