<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushService;
use Illuminate\Console\Command;

class PushTest extends Command
{
    protected $signature = 'push:test {user : id или phone или email}';
    protected $description = 'Отправить тестовый push пользователю';

    public function handle(): int
    {
        $key = $this->argument('user');

        $user = is_numeric($key)
            ? User::find((int) $key)
            : User::where('email', $key)
                ->orWhere('phone_normalized', User::normalizePhone($key))
                ->first();

        if (!$user) {
            $this->error("Пользователь {$key} не найден.");
            return self::FAILURE;
        }

        $subs = $user->pushSubscriptions()->count();
        $this->info("Пользователь #{$user->id} ({$user->name}, {$user->role})");
        $this->line("Подписок: {$subs}");

        if ($subs === 0) {
            $this->warn('Нет активных подписок — пользователь не включил уведомления.');
            return self::FAILURE;
        }

        $this->info('Отправляю push...');

        try {
            PushService::sendToUser($user, [
                'title' => '🔔 Тест push',
                'body'  => 'Уведомления работают · ' . now()->format('d.m H:i:s'),
                'icon'  => '/icons/icon-192.png',
                'url'   => '/',
            ]);
            $this->info('Готово. Проверьте устройства и storage/logs/laravel.log');
        } catch (\Throwable $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
