<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $appointments = Auth::user()->appointments()
            ->where('status', 'planned')
            ->where('start_time', '<', now())
            ->with('service')
            ->orderBy('start_time')
            ->get();

        if ($appointments->isEmpty()) {
            return redirect()->route('dashboard');
        }

        $step = $request->query('step', 'all'); // 'all' или 'individual'

        return view('attendance.index', compact('appointments', 'step'));
    }

    public function process(Request $request)
    {
        $action = $request->input('action');
        $appointmentIds = $request->input('appointment_ids', []);

        // Преобразуем строку с ID в массив, если нужно
        if (is_string($appointmentIds)) {
            $appointmentIds = explode(',', $appointmentIds);
        }

        $appointments = Auth::user()->appointments()
            ->whereIn('id', $appointmentIds)
            ->where('status', 'planned')
            ->get();

        if ($action === 'all_arrived') {
            foreach ($appointments as $appointment) {
                $appointment->update(['status' => 'completed']);
            }
        } elseif ($action === 'individual') {
            $statuses = $request->input('statuses', []);
            foreach ($appointments as $appointment) {
                if (isset($statuses[$appointment->id])) {
                    $newStatus = $statuses[$appointment->id] === 'arrived' ? 'completed' : 'no_show';
                    $appointment->update(['status' => $newStatus]);
                }
            }
        }

        return redirect()->route('dashboard')
            ->with('success', 'Спасибо! Записи обновлены.');
    }
}
