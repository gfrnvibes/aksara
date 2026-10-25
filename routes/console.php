<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Nightly backup to every disk in BACKUP_DISKS (local and Google Drive), old archives pruned first,
| and a morning health check that e-mails BACKUP_NOTIFICATION_EMAIL when a backup is missing or too large.
*/
Schedule::command('backup:clean')->dailyAt('01:00')->timezone('Asia/Jakarta')->onOneServer();
Schedule::command('backup:run')->dailyAt('01:30')->timezone('Asia/Jakarta')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('07:00')->timezone('Asia/Jakarta');

Schedule::command('activitylog:clean')->weekly();

if (config('queue.run_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1')
        ->everyMinute()
        ->withoutOverlapping();
}
