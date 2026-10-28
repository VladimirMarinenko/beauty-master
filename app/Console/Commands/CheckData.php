<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckData extends Command
{
    protected $signature = 'data:check';
    protected $description = 'Проверка данных после миграций';

    public function handle(): int
    {
        $this->info('=== Мастера ===');
        $masters = User::where('role', 'master')->get(['id', 'name', 'slug', 'invite_token', 'email']);

        if ($masters->isEmpty()) {
            $this->warn('Мастеров нет.');
        } else {
            $this->table(
                ['id', 'name', 'slug', 'invite_token', 'email'],
                $masters->map(fn($u) => [
                    $u->id, $u->name, $u->slug ?: '(пусто)', $u->invite_token ?: '(пусто)', $u->email,
                ])->toArray()
            );
        }

        $this->info('');
        $this->info('=== Клиенты ===');
        $clients = User::where('role', 'client')->get(['id', 'name', 'email', 'phone']);

        if ($clients->isEmpty()) {
            $this->warn('Клиентов нет.');
        } else {
            $this->table(
                ['id', 'name', 'email', 'phone'],
                $clients->map(fn($u) => [$u->id, $u->name, $u->email, $u->phone])->toArray()
            );
        }

        $this->info('');
        $this->info('=== Счётчики ===');
        $this->line('users: ' . User::count());
        $this->line('clients (карточки у мастеров): ' . \App\Models\Client::count());
        $this->line('appointments: ' . \App\Models\Appointment::count());

        return self::SUCCESS;
    }
}
