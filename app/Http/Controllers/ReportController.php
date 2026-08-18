<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Определяем период (по умолчанию текущий месяц)
        $period = $request->input('period', 'month');
        $now = Carbon::now();
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        if ($period === 'week') {
            $start = $now->copy()->startOfWeek();
            $end = $now->copy()->endOfWeek();
        } elseif ($period === 'year') {
            $start = $now->copy()->startOfYear();
            $end = $now->copy()->endOfYear();
        }

        // 1. Средний чек: средняя стоимость услуги среди завершённых записей
        $averageCheck = Appointment::where('appointments.user_id', Auth::id())
                ->where('appointments.status', 'completed')
                ->whereBetween('appointments.start_time', [$start, $end])
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->avg('services.price') ?? 0;

        // 2. Загрузка по дням (количество записей по дням)
        $appointmentsPerDay = Appointment::where('user_id', Auth::id())
            ->whereBetween('start_time', [$start, $end])
            ->whereIn('status', ['planned', 'completed'])
            ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->date => $item->count];
            });

        // 3. Доходы и расходы по дням (для графика)
        $transactionsPerDay = Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('DATE(transaction_date) as date,
                         SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
                         SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 4. Диаграмма категорий расходов
        $expenseCategories = Transaction::where('user_id', Auth::id())
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // 5. Отчёт по услугам (количество и выручка)
        $serviceReport = Appointment::where('appointments.user_id', Auth::id())
            ->where('appointments.status', 'completed')
            ->whereBetween('appointments.start_time', [$start, $end])
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->selectRaw('services.name, COUNT(appointments.id) as count, SUM(services.price) as revenue')
            ->groupBy('services.name')
            ->orderByDesc('revenue')
            ->get();

        // Подготовка данных для графиков Chart.js
        $dates = collect();
        $daysRange = $start->copy()->startOfDay()->daysUntil($end->copy()->endOfDay());
        foreach ($daysRange as $day) {
            $dates->push($day->format('Y-m-d'));
        }

        $incomeData = [];
        $expenseData = [];
        $appointmentsData = [];
        foreach ($dates as $date) {
            $transaction = $transactionsPerDay->firstWhere('date', $date);
            $incomeData[] = $transaction->income ?? 0;
            $expenseData[] = $transaction->expense ?? 0;
            $appointmentsData[] = $appointmentsPerDay[$date] ?? 0;
        }

        return view('reports.index', compact(
            'period',
            'averageCheck',
            'dates',
            'incomeData',
            'expenseData',
            'appointmentsData',
            'expenseCategories',
            'serviceReport',
            'start',
            'end'
        ));
    }
}
