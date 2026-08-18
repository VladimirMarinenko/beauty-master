<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function index()
    {
        $services = Auth::user()->services()->orderBy('name')->get();
        return view('appointments.index', compact('services'));
    }

    public function events()
    {
        $appointments = Auth::user()->appointments()
            ->with('service')
            ->get()
            ->map(function ($appointment) {
                $serviceIds = $appointment->service_ids ?: [$appointment->service_id];
                $serviceNames = Service::whereIn('id', $serviceIds)->pluck('name')->implode(', ');

                return [
                    'id' => $appointment->id,
                    'title' => $appointment->client_name . ' — ' . $serviceNames,
                    'start' => $appointment->start_time->format('Y-m-d\TH:i:s'),
                    'end' => $appointment->end_time->format('Y-m-d\TH:i:s'),
                    'backgroundColor' => $this->getStatusColor($appointment->status),
                    'borderColor' => $this->getStatusColor($appointment->status),
                    'extendedProps' => [
                        'client_phone' => $appointment->client_phone,
                        'client_email' => $appointment->client_email,
                        'status' => $appointment->status,
                        'notes' => $appointment->notes,
                        'service_id' => $appointment->service_id,
                    ],
                ];
            });

        return response()->json($appointments);
    }

    public function create(Request $request)
    {
        $services = Auth::user()->services()->orderBy('name')->get();
        $start = $request->query('start');
        $end = $request->query('end');
        return view('appointments.create', compact('services', 'start', 'end'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_ids' => 'required|string',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'start_time' => 'required|date_format:Y-m-d H:i',
            'end_time' => 'required|date_format:Y-m-d H:i|after:start_time',
            'status' => 'required|in:planned,completed,cancelled,no_show',
            'notes' => 'nullable|string',
        ]);

        $serviceIds = json_decode($validated['service_ids'], true);
        if (!is_array($serviceIds) || empty($serviceIds)) {
            return back()->withErrors(['service_ids' => 'Выберите хотя бы одну услугу']);
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        if ($services->count() != count($serviceIds) || $services->where('user_id', '!=', Auth::id())->isNotEmpty()) {
            return back()->withErrors(['service_ids' => 'Одна из услуг не найдена или принадлежит другому мастеру']);
        }

        $validated['user_id'] = Auth::id();
        $validated['service_id'] = $serviceIds[0];
        $validated['service_ids'] = $serviceIds;
        $validated['total_price'] = $services->sum('price');
        $validated['total_duration'] = $services->sum('duration');
        $validated['start_time'] = Carbon::parse($validated['start_time']);
        $validated['end_time'] = Carbon::parse($validated['end_time']);

        if ($this->hasOverlap(Auth::id(), $validated['start_time'], $validated['end_time'])) {
            return back()
                ->withErrors(['start_time' => 'Выбранное время пересекается с другой записью'])
                ->withInput();
        }

        Appointment::create($validated);

        return redirect()->route('appointments.index')
            ->with('success', 'Запись добавлена!');
    }

    public function edit(Appointment $appointment)
    {
        $this->authorize('update', $appointment);
        $services = Auth::user()->services()->orderBy('name')->get();
        return view('appointments.edit', compact('appointment', 'services'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        $validated = $request->validate([
            'service_ids' => 'required|string',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'start_time' => 'required|date_format:Y-m-d H:i',
            'end_time' => 'required|date_format:Y-m-d H:i|after:start_time',
            'status' => 'required|in:planned,completed,cancelled,no_show',
            'notes' => 'nullable|string',
        ]);

        $serviceIds = json_decode($validated['service_ids'], true);
        if (!is_array($serviceIds) || empty($serviceIds)) {
            return back()->withErrors(['service_ids' => 'Выберите хотя бы одну услугу']);
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        if ($services->count() != count($serviceIds) || $services->where('user_id', '!=', Auth::id())->isNotEmpty()) {
            return back()->withErrors(['service_ids' => 'Одна из услуг не найдена или принадлежит другому мастеру']);
        }

        $validated['service_id'] = $serviceIds[0];
        $validated['service_ids'] = $serviceIds;
        $validated['total_price'] = $services->sum('price');
        $validated['total_duration'] = $services->sum('duration');
        $validated['start_time'] = Carbon::parse($validated['start_time']);
        $validated['end_time'] = Carbon::parse($validated['end_time']);

        if ($this->hasOverlap(Auth::id(), $validated['start_time'], $validated['end_time'], $appointment->id)) {
            return back()
                ->withErrors(['start_time' => 'Выбранное время пересекается с другой записью'])
                ->withInput();
        }

        $appointment->update($validated);

        if ($appointment->status === 'completed' && $appointment->wasChanged('status')) {
            $existingTransaction = Transaction::where('appointment_id', $appointment->id)->first();
            if (!$existingTransaction) {
                $appointment->user->transactions()->create([
                    'type' => 'income',
                    'amount' => $appointment->total_price,
                    'transaction_date' => now(),
                    'category' => 'Доход от услуги',
                    'description' => 'Запись: ' . $appointment->client_name,
                    'appointment_id' => $appointment->id,
                ]);
            }
        }

        return redirect()->route('appointments.index')
            ->with('success', 'Запись обновлена!');
    }

    public function destroy(Appointment $appointment)
    {
        $this->authorize('delete', $appointment);
        $appointment->delete();

        return redirect()->route('appointments.index')
            ->with('success', 'Запись удалена!');
    }

    private function hasOverlap($userId, $start, $end, $excludeId = null)
    {
        $query = Appointment::where('user_id', $userId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function checkOverlap(Request $request)
    {
        $start = Carbon::parse($request->start_time);
        $end = Carbon::parse($request->end_time);
        $excludeId = $request->appointment_id ?? null;

        $available = !$this->hasOverlap(Auth::id(), $start, $end, $excludeId);

        return response()->json(['available' => $available]);
    }

    public function availableSlots(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date_format:Y-m-d',
            'appointment_id' => 'nullable|integer',
            'duration' => 'nullable|integer|min:5'
        ]);

        $service = Service::findOrFail($request->service_id);
        if ($service->user_id !== Auth::id()) {
            return response()->json(['error' => 'Услуга не найдена'], 403);
        }

        $duration = $request->input('duration', $service->duration);

        $date = Carbon::parse($request->date)->startOfDay();
        $excludeId = $request->appointment_id;

        $startOfDay = $date->copy()->setTime(6, 0);
        $endOfDay = $date->copy()->setTime(22, 0);
        $step = 15;

        $busyIntervals = Appointment::where('user_id', Auth::id())
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'no_show')
            ->whereDate('start_time', $date)
            ->when($excludeId, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->get(['start_time', 'end_time'])
            ->map(function ($app) {
                return [
                    'start' => $app->start_time->subMinutes(15),
                    'end' => $app->end_time->addMinutes(15),
                ];
            });

        $slots = [];
        $current = $startOfDay->copy();

        while ($current->copy()->addMinutes($duration) <= $endOfDay) {
            $slotStart = $current->copy();
            $slotEnd = $slotStart->copy()->addMinutes($duration);

            $isFree = true;
            foreach ($busyIntervals as $busy) {
                if ($slotStart < $busy['end'] && $slotEnd > $busy['start']) {
                    $isFree = false;
                    break;
                }
            }

            if ($isFree) {
                $slots[] = [
                    'start' => $slotStart->format('Y-m-d H:i'),
                    'end' => $slotEnd->format('Y-m-d H:i'),
                ];
            }

            $current->addMinutes($step);
        }

        return response()->json(['slots' => $slots]);
    }

    public function sync(Request $request)
    {
        $validated = $request->validate([
            'service_ids' => 'required|string',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'start_time' => 'required|date_format:Y-m-d H:i',
            'end_time' => 'required|date_format:Y-m-d H:i|after:start_time',
            'status' => 'required|in:planned,completed,cancelled,no_show',
            'notes' => 'nullable|string',
            'appointment_id' => 'nullable|integer',
            '_method' => 'nullable|string',
        ]);

        $serviceIds = json_decode($validated['service_ids'], true);
        if (!is_array($serviceIds) || empty($serviceIds)) {
            return response()->json(['error' => 'Не выбраны услуги'], 422);
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        if ($services->count() != count($serviceIds) || $services->where('user_id', '!=', Auth::id())->isNotEmpty()) {
            return response()->json(['error' => 'Unauthorized services'], 403);
        }

        $validated['service_id'] = $serviceIds[0];
        $validated['service_ids'] = $serviceIds;
        $validated['total_price'] = $services->sum('price');
        $validated['total_duration'] = $services->sum('duration');
        $validated['start_time'] = Carbon::parse($validated['start_time']);
        $validated['end_time'] = Carbon::parse($validated['end_time']);

        // Обновление существующей записи
        if (!empty($validated['appointment_id'])) {
            $appointment = Appointment::findOrFail($validated['appointment_id']);
            if ($appointment->user_id !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            if ($this->hasOverlap(Auth::id(), $validated['start_time'], $validated['end_time'], $appointment->id)) {
                return response()->json(['error' => 'Overlap'], 422);
            }

            $appointment->update($validated);

            return response()->json(['success' => true]);
        }

        // Создание новой записи
        $validated['user_id'] = Auth::id();

        if ($this->hasOverlap(Auth::id(), $validated['start_time'], $validated['end_time'])) {
            return response()->json(['error' => 'Overlap'], 422);
        }

        Appointment::create($validated);

        return response()->json(['success' => true]);
    }

    private function getStatusColor($status)
    {
        switch ($status) {
            case 'planned': return '#3b82f6';
            case 'completed': return '#10b981';
            case 'cancelled': return '#ef4444';
            case 'no_show': return '#f59e0b';
            default: return '#6b7280';
        }
    }
}
