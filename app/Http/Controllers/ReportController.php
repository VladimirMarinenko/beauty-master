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

        // === Только выполненные записи за период ===
        $completedAppointments = Appointment::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->whereBetween('start_time', [$start, $end])
            ->get();

        $completedAppointmentsCount = $completedAppointments->count();
        $totalRevenue = (float) $completedAppointments->sum('total_price');
        $averageCheck = $completedAppointmentsCount > 0
            ? $totalRevenue / $completedAppointmentsCount
            : 0.0;

        // === Выполненные записи по дням ===
        $appointmentsPerDay = Appointment::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->whereBetween('start_time', [$start, $end])
            ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->date => $item->count];
            });

        // === Транзакции по дням ===
        $transactionsPerDay = Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('DATE(transaction_date) as date,
                         SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income,
                         SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // === Расходы по категориям ===
        $expenseCategories = Transaction::where('user_id', Auth::id())
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // === Разбивка по услугам (доля выручки, пропорционально числу услуг в записи) ===
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

        $serviceStats = [];
        foreach ($completedAppointments as $app) {
            $ids = $app->service_ids ?: [$app->service_id];
            if (is_string($ids)) $ids = json_decode($ids, true);
            if (!is_array($ids) || empty($ids)) $ids = [$app->service_id];
            $ids = array_values(array_unique($ids));
            if (count($ids) === 0) continue;

            $share = 1 / count($ids);

            foreach ($ids as $sid) {
                if (!isset($serviceStats[$sid])) {
                    $serviceStats[$sid] = ['count' => 0, 'revenue' => 0];
                }
                $serviceStats[$sid]['count']++;
                $serviceStats[$sid]['revenue'] += (float) $app->total_price * $share;
            }
        }

        $serviceReport = collect($serviceStats)
            ->map(function ($data, $sid) use ($serviceNames) {
                return (object)[
                    'name' => $serviceNames[$sid] ?? 'Услуга #' . $sid,
                    'count' => $data['count'],
                    'revenue' => round($data['revenue'], 2),
                    'avg_per_appointment' => $data['count'] > 0
                        ? round($data['revenue'] / $data['count'], 2)
                        : 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        // === Массив дат для графика ===
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
            'totalRevenue',
            'completedAppointmentsCount',
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
