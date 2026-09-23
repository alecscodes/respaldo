<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SnapshotDatabase extends Command
{
    protected $signature = 'database:snapshot';

    protected $description = 'Copy the database to the backup disk, so a dead server can be restored from it';

    public function handle(): int
    {
        $path = Storage::disk('backups')->path('database.sqlite');

        // Write to a temp file and rename, so a crash mid-copy never leaves a broken snapshot.
        File::ensureDirectoryExists(dirname($path));
        File::delete("$path.tmp");
        DB::statement('VACUUM INTO ?', ["$path.tmp"]);
        File::move("$path.tmp", $path);

        $this->info("Database copied to {$path}");

        return self::SUCCESS;
    }
}
