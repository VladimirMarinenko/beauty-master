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

        $step = $request->query('step', 'all');

        return view('attendance.index', compact('appointments', 'step'));
    }

    public function process(Request $request)
    {
        $action = $request->input('action');
        $appointmentIds = $request->input('appointment_ids', []);

        if (is_string($appointmentIds)) {
            $appointmentIds = array_filter(explode(',', $appointmentIds));
        }

        if (empty($appointmentIds)) {
            return redirect()->route('dashboard')
                ->with('error', 'Не выбрано ни одной записи.');
        }

        $appointments = Auth::user()->appointments()
            ->whereIn('id', $appointmentIds)
            ->where('status', 'planned')
            ->get();

        if ($appointments->isEmpty()) {
            return redirect()->route('dashboard')
                ->with('success', 'Все записи уже обновлены.');
        }

        if ($action === 'all_arrived') {
            foreach ($appointments as $appointment) {
                $appointment->update(['status' => 'completed']);
                $appointment->syncTransaction();
            }
        } elseif ($action === 'individual') {
            $statuses = $request->input('statuses', []);

            foreach ($appointments as $appointment) {
                $choice = $statuses[$appointment->id] ?? 'arrived';
                $newStatus = $choice === 'arrived' ? 'completed' : 'cancelled';

                $appointment->update(['status' => $newStatus]);
                $appointment->syncTransaction();
            }
        }

        return redirect()->route('dashboard')
            ->with('success', 'Спасибо! Записи обновлены.');
    }
}
