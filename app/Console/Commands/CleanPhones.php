<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class CleanPhones extends Command
{
    protected $signature = 'data:clean-phones';
    protected $description = 'Очистить мусорные телефоны (placeholder из IMask)';

    public function handle(): int
    {
        $this->info('Сканирую клиентов...');

        $bad = Client::where(function ($q) {
            $q->where('phone', 'like', '%___%')
                ->orWhere('phone', 'like', '%__-__%')
                ->orWhere('phone', 'like', '%000-00-00%')
                ->orWhere('phone2', 'like', '%___%')
                ->orWhere('phone2', 'like', '%__-__%')
                ->orWhere('phone2', 'like', '%000-00-00%');
        });

        $count = $bad->count();

        if ($count === 0) {
            $this->info('Мусорных телефонов не найдено.');
            return self::SUCCESS;
        }

        $this->warn("Найдено клиентов с мусорными телефонами: {$count}");
        $this->table(
            ['id', 'name', 'phone', 'phone2'],
            $bad->limit(10)->get(['id', 'name', 'phone', 'phone2'])->map(fn($c) => [
                $c->id, $c->name, $c->phone ?? '—', $c->phone2 ?? '—',
            ])->toArray()
        );

        if (!$this->confirm('Очистить эти поля (phone, phone2 → NULL)?', true)) {
            $this->info('Отменено.');
            return self::SUCCESS;
        }

        $bad->update(['phone' => null, 'phone2' => null]);

        $this->info("Очищено клиентов: {$count}");
        return self::SUCCESS;
    }
}
