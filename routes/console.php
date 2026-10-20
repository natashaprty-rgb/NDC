<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. A single cron entry runs them:
|   * * * * * cd /path/to/ndc && php artisan schedule:run >> /dev/null 2>&1
*/

// Heartbeat shown on the System Health page.
Schedule::call(fn () => Cache::forever('ndc.scheduler.heartbeat', now()))->everyFiveMinutes()->name('scheduler-heartbeat');

Schedule::command('ndc:send-reminders')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('ndc:backup --prune')->dailyAt('01:30')->withoutOverlapping()->onOneServer();
Schedule::command('ndc:prune')->weekly()->sundays()->at('03:00');
Schedule::command('queue:prune-failed --hours=720')->weekly();
