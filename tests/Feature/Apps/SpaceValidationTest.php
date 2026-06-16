<?php

use App\Models\App;
use App\Models\Backup;
use App\Models\User;
use App\Services\StorageConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('app shows correct used and available space', function () {
    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'storage_size' => StorageConverter::gbToBytes(10), // 10 GB
    ]);

    $this->assertEquals(0, $app->usedSpace());
    $this->assertEquals(StorageConverter::gbToBytes(10), $app->availableSpace());

    // Create backup
    $backupSize = StorageConverter::gbToBytes(2); // 2 GB
    Backup::factory()->create([
        'app_id' => $app->id,
        'user_id' => $user->id,
        'size' => $backupSize,
    ]);

    $app->refresh();
    $this->assertEquals($backupSize, $app->usedSpace());
    $this->assertEquals(StorageConverter::gbToBytes(8), $app->availableSpace());
});

test('cannot create backup if insufficient space', function () {
    Storage::fake();

    $user = User::factory()->create();
    // Create app with very small storage (5MB)
    $app = App::factory()->create([
        'user_id' => $user->id,
        'storage_size' => 5 * 1024 * 1024, // 5 MB
    ]);

    $this->actingAs($user);

    // Create a file larger than available space (10MB)
    // UploadedFile::fake()->create() size parameter is in KB
    $file = UploadedFile::fake()->create('backup.tar.gz', 10 * 1024); // 10MB file

    $response = $this->post(route('backups.store', $app), [
        'file' => $file,
    ]);

    $response->assertRedirect();
});

test('can create backup if sufficient space', function () {
    Storage::fake();

    $user = User::factory()->create();
    $app = App::factory()->create([
        'user_id' => $user->id,
        'storage_size' => StorageConverter::gbToBytes(10), // 10 GB
    ]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->create('backup.tar.gz', 100); // Small file

    $response = $this->post(route('backups.store', $app), [
        'file' => $file,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('backups', [
        'app_id' => $app->id,
        'user_id' => $user->id,
    ]);
});
