<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('git:update')->everyMinute();
Schedule::command('backups:check-missed')->hourly();
Schedule::command('backups:cleanup-stale-chunks')->hourly();
