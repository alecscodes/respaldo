<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('database snapshot is written to the backups disk', function () {
    Storage::fake('backups');
    DB::commit(); // VACUUM cannot run inside the RefreshDatabase transaction

    $this->artisan('database:snapshot')->assertSuccessful();

    expect(file_get_contents(Storage::disk('backups')->path('database.sqlite'), length: 15))->toBe('SQLite format 3');
});
