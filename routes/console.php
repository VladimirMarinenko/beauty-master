<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-daily-appointments-notification')->dailyAt('21:00');
Schedule::command('push:send-reminders')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('tests:notify')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/tests-notify.log'));
