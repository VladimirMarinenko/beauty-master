<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TransactionController extends Controller
{
    // Список транзакций с фильтрами
    public function index(Request $request)
    {
        $query = Auth::user()->transactions()->orderBy('transaction_date', 'desc');

        // Фильтр по типу
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Фильтр по периоду
        if ($request->filled('period')) {
            $now = Carbon::now();
            switch ($request->period) {
                case 'week':
                    $start = $now->copy()->startOfWeek();
                    $end = $now->copy()->endOfWeek();
                    break;
                case 'month':
                    $start = $now->copy()->startOfMonth();
                    $end = $now->copy()->endOfMonth();
                    break;
                case 'year':
                    $start = $now->copy()->startOfYear();
                    $end = $now->copy()->endOfYear();
                    break;
                default:
                    $start = null;
                    $end = null;
            }
            if ($start && $end) {
                $query->whereBetween('transaction_date', [$start, $end]);
            }
        }

        $statsQuery = clone $query;
        $totalIncome = (clone $statsQuery)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $statsQuery)->where('type', 'expense')->sum('amount');
        $netProfit = $totalIncome - $totalExpense;

        // Пагинация для списка
        $transactions = $query->paginate(20);

        return view('transactions.index', compact('transactions', 'totalIncome', 'totalExpense', 'netProfit'));
    }

    // Форма создания
    public function create()
    {
        return view('transactions.create');
    }

    // Сохранение
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date_format:Y-m-d H:i',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['is_recurring'] = $request->boolean('is_recurring');
        $validated['transaction_date'] = Carbon::parse($validated['transaction_date']);

        Transaction::create($validated);

        return redirect()->route('transactions.index')
            ->with('success', 'Транзакция добавлена!');
    }

    // Форма редактирования
    public function edit(Transaction $transaction)
    {
        $this->authorize('update', $transaction);
        return view('transactions.edit', compact('transaction'));
    }

    // Обновление
    public function update(Request $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date_format:Y-m-d H:i',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
        ]);

        $validated['transaction_date'] = Carbon::parse($validated['transaction_date']);
        $validated['is_recurring'] = $request->boolean('is_recurring');
        $transaction->update($validated);

        return redirect()->route('transactions.index')
            ->with('success', 'Транзакция обновлена!');
    }

    // Удаление
    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);
        $transaction->delete();

        return redirect()->route('transactions.index')
            ->with('success', 'Транзакция удалена!');
    }
}
