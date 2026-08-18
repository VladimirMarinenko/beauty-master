<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateRecurringTransactions extends Command
{
    protected $signature = 'transactions:create-recurring';
    protected $description = 'Создаёт регулярные транзакции для текущего месяца';

    public function handle()
    {
        $now = Carbon::now();
        $firstDayOfMonth = $now->copy()->startOfMonth();

        // Получаем все регулярные транзакции, у которых ещё не создана копия за этот месяц
        $recurringTransactions = Transaction::where('is_recurring', true)
            ->whereNull('appointment_id') // только ручные расходы/доходы
            ->whereMonth('transaction_date', '!=', $now->month)
            ->orWhere(function ($query) use ($now) {
                $query->where('is_recurring', true)
                    ->whereNull('appointment_id')
                    ->whereYear('transaction_date', '!=', $now->year);
            })
            ->get();

        $count = 0;
        foreach ($recurringTransactions as $transaction) {
            // Проверяем, не существует ли уже копия за текущий месяц
            $exists = Transaction::where('user_id', $transaction->user_id)
                ->where('is_recurring', true)
                ->whereNull('appointment_id')
                ->where('description', $transaction->description)
                ->where('amount', $transaction->amount)
                ->whereMonth('transaction_date', $now->month)
                ->whereYear('transaction_date', $now->year)
                ->exists();

            if (!$exists) {
                $newTransaction = $transaction->replicate();
                $newTransaction->transaction_date = $firstDayOfMonth->copy()->setTime(
                    $transaction->transaction_date->hour,
                    $transaction->transaction_date->minute,
                    $transaction->transaction_date->second
                );
                $newTransaction->is_recurring = true; // оставляем регулярность
                $newTransaction->save();
                $count++;
            }
        }

        $this->info("Создано регулярных транзакций: {$count}");
        return 0;
    }
}
