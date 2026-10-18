<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. Production servers must run the Laravel scheduler every minute:
|   * * * * * cd /var/www/ndc-sms && php artisan schedule:run >> /dev/null 2>&1
| and a queue worker (see docs/06-deployment.md).
*/

// Lets the System health page confirm that cron is running.
Schedule::call(fn () => Cache::forever('ndc.scheduler.heartbeat', now()))->everyMinute()->name('scheduler-heartbeat');

Schedule::command('ndc:send-reminders')->hourly()->withoutOverlapping();

Schedule::command('ndc:backup --files --keep=14')->dailyAt('01:30')->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=720')->daily();
