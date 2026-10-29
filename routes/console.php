<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Напоминания о записях на завтра — каждый день в 20:00 по Москве
Schedule::command('app:send-daily-appointments-notification')
    ->dailyAt('20:00')
    ->timezone('Europe/Moscow')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/schedule.log'));

// Push за 2 часа до приёма — от таймзоны не зависит
Schedule::command('push:send-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/schedule.log'));

// Регулярные транзакции — каждый день в 01:00 по Москве
Schedule::command('transactions:create-recurring')
    ->dailyAt('01:00')
    ->timezone('Europe/Moscow')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/schedule.log'));

// Тесты в Telegram — каждые 30 минут (после фикса phpunit.xml)
 Schedule::command('tests:notify')
     ->everyThirtyMinutes()
     ->withoutOverlapping()
     ->runInBackground()
     ->appendOutputTo(storage_path('logs/tests-notify.log'));
