<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
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

        // 1. Средний чек: среднее по фактической стоимости записей (total_price)
        $averageCheck = Appointment::where('user_id', Auth::id())
                ->where('status', 'completed')
                ->whereBetween('start_time', [$start, $end])
                ->avg('total_price') ?? 0;

        // 2. Загрузка по дням
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

        // 3. Доходы/расходы по дням
        $transactionsPerDay = Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('DATE(transaction_date) as date,
                         SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
                         SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 4. Категории расходов
        $expenseCategories = Transaction::where('user_id', Auth::id())
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // 5. Отчёт по услугам — через service_ids + фактическая стоимость
        $completedAppointments = Appointment::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->whereBetween('start_time', [$start, $end])
            ->get();

        // Собираем все ID услуг, чтобы одним запросом получить названия
        $allServiceIds = [];
        foreach ($completedAppointments as $app) {
            $ids = $app->service_ids ?: [$app->service_id];
            if (is_string($ids)) $ids = json_decode($ids, true);
            if (!is_array($ids) || empty($ids)) $ids = [$app->service_id];
            foreach ($ids as $sid) {
                $allServiceIds[$sid] = true;
            }
        }
        $serviceNames = Service::whereIn('id', array_keys($allServiceIds))
            ->pluck('name', 'id');

        // Считаем выручку. total_price делится поровну между услугами в записи
        $serviceStats = [];
        foreach ($completedAppointments as $app) {
            $ids = $app->service_ids ?: [$app->service_id];
            if (is_string($ids)) $ids = json_decode($ids, true);
            if (!is_array($ids) || empty($ids)) $ids = [$app->service_id];
            $ids = array_values(array_unique($ids));
            $share = count($ids) > 0 ? 1 / count($ids) : 0;

            foreach ($ids as $sid) {
                if (!isset($serviceStats[$sid])) {
                    $serviceStats[$sid] = ['count' => 0, 'revenue' => 0];
                }
                $serviceStats[$sid]['count']++;
                $serviceStats[$sid]['revenue'] += $app->total_price * $share;
            }
        }

        $serviceReport = collect($serviceStats)
            ->map(function ($data, $sid) use ($serviceNames) {
                return (object)[
                    'name' => $serviceNames[$sid] ?? 'Услуга #' . $sid,
                    'count' => $data['count'],
                    'revenue' => round($data['revenue'], 2),
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        // Подготовка графиков
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
