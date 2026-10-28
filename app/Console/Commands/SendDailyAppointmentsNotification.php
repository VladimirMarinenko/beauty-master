<?php

namespace App\Console\Commands;

use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyAppointmentsNotification extends Command
{
    protected $signature = 'app:send-daily-appointments-notification';
    protected $description = 'Напоминания о записях на завтра (мастерам и клиентам)';

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow();
        $dateStr  = $tomorrow->format('Y-m-d');

        $appointments = Appointment::whereDate('start_time', $dateStr)
            ->where('status', 'planned')
            ->with(['master', 'clientUser', 'service'])
            ->orderBy('start_time')
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('Нет записей на завтра.');
            return self::SUCCESS;
        }

        $sent = 0;

        // === Мастерам: все записи на завтра ===
        $byMaster = $appointments->whereNotNull('master_id')->groupBy('master_id');
        foreach ($byMaster as $masterId => $list) {
            $master = $list->first()->master;
            if (!$master) continue;

            $lines = $list->map(fn($a) =>
                $a->start_time->format('H:i')
                . ' — ' . $a->client_name
                . ' (' . ($a->service?->name ?? 'услуга удалена') . ')'
            )->implode("\n");

            SendPushNotification::dispatch($master, [
                'title' => '📅 Записи на завтра: ' . $list->count(),
                'body'  => $lines,
                'icon'  => '/icons/icon-192.png',
                'url'   => '/appointments',
            ]);
            $sent++;
        }

        // === Клиентам: только их записи ===
        $byClient = $appointments->whereNotNull('client_user_id')->groupBy('client_user_id');
        foreach ($byClient as $clientUserId => $list) {
            $clientUser = $list->first()->clientUser;
            if (!$clientUser) continue;

            $lines = $list->map(fn($a) =>
                $a->start_time->format('H:i')
                . ' — ' . ($a->master?->name ?? 'мастер')
                . ' (' . ($a->service?->name ?? 'услуга удалена') . ')'
            )->implode("\n");

            SendPushNotification::dispatch($clientUser, [
                'title' => '📅 Ваши записи на завтра: ' . $list->count(),
                'body'  => $lines,
                'icon'  => '/icons/icon-192.png',
                'url'   => '/me/appointments',
            ]);
            $sent++;
        }

        $this->info("Записей: {$appointments->count()}, задач в очередь: {$sent}");
        return self::SUCCESS;
    }
}
