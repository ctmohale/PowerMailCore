<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue worker — runs every minute via the scheduler.
// On cPanel (or any server) you only need ONE cron entry:
//   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
// That single cron handles this and any future scheduled tasks automatically.
Schedule::command('queue:work', [
    '--queue'    => 'marketing',
    '--tries'    => '3',
    '--timeout'  => '120',
    '--sleep'    => '3',
    '--max-time' => '55',   // Exit after 55s so the next cron minute starts fresh
    '--stop-when-empty',    // Exit quickly when no jobs are waiting
])->everyMinute()->withoutOverlapping(2)->runInBackground();
