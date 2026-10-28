<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ShowClients extends Command
{
    protected $signature = 'user:show-clients';
    protected $description = 'Показать всех клиентов с телефонами';

    public function handle(): int
    {
        $clients = User::where('role', 'client')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'phone', 'phone_normalized', 'password']);

        if ($clients->isEmpty()) {
            $this->warn('Клиентов нет.');
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'name', 'email', 'phone', 'phone_norm', 'hash_len'],
            $clients->map(fn($u) => [
                $u->id,
                $u->name,
                $u->email ?: '—',
                $u->phone ?: '—',
                $u->phone_normalized ?: '—',
                strlen($u->getRawOriginal('password') ?? ''),
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
