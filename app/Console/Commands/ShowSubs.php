<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;

class ShowSubs extends Command
{
    protected $signature = 'push:show-subs';
    protected $description = 'Показать все push-подписки';

    public function handle(): int
    {
        $subs = PushSubscription::with('user')->get();

        if ($subs->isEmpty()) {
            $this->warn('Подписок нет.');
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'user_id', 'name', 'role', 'endpoint'],
            $subs->map(fn($s) => [
                $s->id,
                $s->user_id,
                $s->user?->name ?? '—',
                $s->user?->role ?? '—',
                substr($s->endpoint, 0, 40) . '...',
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
