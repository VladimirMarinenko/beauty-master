<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendDailyAppointmentsNotification extends Command
{
    protected $signature = 'app:send-daily-appointments-notification';
    protected $description = 'Отправляет push-уведомления о записях на завтра';

    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        // Получаем пользователей, у которых есть записи на завтра
        $users = User::whereHas('appointments', function ($query) use ($tomorrow) {
            $query->whereDate('start_time', $tomorrow)
                ->where('status', 'planned');
        })
            ->with(['appointments' => function ($query) use ($tomorrow) {
                $query->whereDate('start_time', $tomorrow)
                    ->where('status', 'planned')
                    ->with('service')
                    ->orderBy('start_time');
            }, 'pushSubscriptions'])
            ->get();

        if ($users->isEmpty()) {
            $this->info('Нет пользователей с записями на завтра.');
            return 0;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => env('APP_URL', 'http://127.0.0.1:8000'),
                'publicKey' => env('VAPID_PUBLIC_KEY'),
                'privateKey' => env('VAPID_PRIVATE_KEY'),
            ],
        ]);

        foreach ($users as $user) {
            if ($user->pushSubscriptions->isEmpty()) continue;

            $appointmentsCount = $user->appointments->count();
            $appointmentsList = $user->appointments->map(function ($app) {
                return "{$app->start_time->format('H:i')} — {$app->client_name} ({$app->service->name})";
            })->implode("\n");

            $payload = [
                'title' => "Записи на завтра: {$appointmentsCount}",
                'body' => $appointmentsList,
                'url' => '/appointments'
            ];

            foreach ($user->pushSubscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                    ]),
                    json_encode($payload)
                );
            }
        }

        // Отправляем и смотрим отчёты
        $reports = $webPush->flush();
        $successCount = 0;
        foreach ($reports as $report) {
            if ($report->isSuccess()) {
                $successCount++;
            } else {
                $this->error('Ошибка отправки: ' . $report->getReason());
            }
        }

        $this->info("Отправлено уведомлений: {$successCount}");
        return 0;
    }
}
