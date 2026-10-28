<?php

namespace App\Console\Commands;

use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SendPushReminders extends Command
{
    protected $signature = 'push:send-reminders';
    protected $description = 'Напоминания за 2 часа до приёма (клиенту и мастеру)';

    public function handle(): int
    {
        $from = now()->addHours(2)->subMinutes(15);
        $to   = now()->addHours(2)->addMinutes(15);

        $appointments = Appointment::where('status', 'planned')
            ->whereBetween('start_time', [$from, $to])
            ->with(['master', 'clientUser', 'service'])
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('Записей для напоминания нет.');
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($appointments as $a) {
            $masterName  = $a->master?->name ?? 'мастер';
            $serviceName = $a->service?->name ?? 'услуга';
            $time = $a->start_time->format('H:i');

            if ($a->clientUser) {
                SendPushNotification::dispatch($a->clientUser, [
                    'title' => '⏰ Напоминание о записи',
                    'body'  => 'Сегодня в ' . $time . ' · ' . $masterName . ' · ' . $serviceName,
                    'icon'  => '/icons/icon-192.png',
                    'url'   => '/me/appointments',
                ]);
                $sent++;
                $this->line("  → клиенту #{$a->clientUser->id} ({$a->clientUser->name}) · {$time}");
            }

            if ($a->master) {
                SendPushNotification::dispatch($a->master, [
                    'title' => '⏰ Скоро клиент',
                    'body'  => $a->client_name . ' · ' . $time . ' · ' . $serviceName,
                    'icon'  => '/icons/icon-192.png',
                    'url'   => '/appointments',
                ]);
                $sent++;
                $this->line("  → мастеру #{$a->master->id} ({$a->master->name}) · {$time}");
            }
        }

        $this->info("Записей: {$appointments->count()}, задач в очередь: {$sent}");
        return self::SUCCESS;
    }
}
