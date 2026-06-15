<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:deploy --if-outdated')->everyFiveMinutes();
Schedule::command('backups:check-missed')->hourly();
Schedule::command('backups:cleanup-stale-chunks')->hourly();
Schedule::command('logs:cleanup')->daily();
