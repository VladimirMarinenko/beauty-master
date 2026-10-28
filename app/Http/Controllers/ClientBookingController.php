<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientBookingController extends Controller
{
    public function show(User $master)
    {
        $client = Auth::user();

        if (!$client->isClient() || $master->role !== User::ROLE_MASTER) {
            abort(404);
        }

        $card = $client->myClientCards()->where('master_id', $master->id)->first();
        if (!$card) {
            abort(403, 'Вы не привязаны к этому мастеру.');
        }

        $services = $master->services()->orderBy('name')->get();

        return view('me.book', compact('master', 'card', 'services'));
    }

    public function slots(Request $request, User $master)
    {
        $client = Auth::user();

        if (!$client->isClient() || $master->role !== User::ROLE_MASTER) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $card = $client->myClientCards()->where('master_id', $master->id)->first();
        if (!$card) {
            return response()->json(['error' => 'Not attached'], 403);
        }

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = $master->services()->where('id', $validated['service_id'])->first();
        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        $duration = (int) $service->duration;
        $date = Carbon::parse($validated['date'])->startOfDay();

        $dayConfig = $master->workHoursForDay($date->dayOfWeekIso);
        if (!$dayConfig) {
            return response()->json(['slots' => []]);
        }

        $startOfDay = $date->copy()->setTimeFromTimeString($dayConfig['start']);
        $endOfDay = $date->copy()->setTimeFromTimeString($dayConfig['end']);

        $breakStart = !empty($dayConfig['break_start'])
            ? $date->copy()->setTimeFromTimeString($dayConfig['break_start'])
            : null;

        $breakEnd = !empty($dayConfig['break_end'])
            ? $date->copy()->setTimeFromTimeString($dayConfig['break_end'])
            : null;

        $step = $master->effectiveSlotStep();

        $busyIntervals = Appointment::where('master_id', $master->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('start_time', $date)
            ->get(['start_time', 'end_time'])
            ->map(function ($app) {
                return [
                    'start' => $app->start_time->copy()->subMinutes(15),
                    'end' => $app->end_time->copy()->addMinutes(15),
                ];
            });

        $slots = [];
        $current = $startOfDay->copy();

        while ($current->copy()->addMinutes($duration) <= $endOfDay) {
            $slotStart = $current->copy();
            $slotEnd = $slotStart->copy()->addMinutes($duration);

            $isFree = true;

            if ($breakStart && $breakEnd) {
                if ($slotStart < $breakEnd && $slotEnd > $breakStart) {
                    $isFree = false;
                }
            }

            if ($isFree) {
                foreach ($busyIntervals as $busy) {
                    if ($slotStart < $busy['end'] && $slotEnd > $busy['start']) {
                        $isFree = false;
                        break;
                    }
                }
            }

            if ($isFree) {
                $slots[] = [
                    'start' => $slotStart->format('Y-m-d H:i'),
                    'end' => $slotEnd->format('Y-m-d H:i'),
                    'time' => $slotStart->format('H:i'),
                ];
            }

            $current->addMinutes($step);
        }

        return response()->json(['slots' => $slots]);
    }

    public function book(Request $request, User $master)
    {
        $client = Auth::user();

        if (!$client->isClient() || $master->role !== User::ROLE_MASTER) {
            abort(404);
        }

        $card = $client->myClientCards()->where('master_id', $master->id)->first();
        if (!$card) {
            abort(403, 'Вы не привязаны к этому мастеру.');
        }

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'start_time' => ['required', 'date_format:Y-m-d H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = $master->services()->where('id', $validated['service_id'])->first();
        if (!$service) {
            return back()->withErrors(['service_id' => 'Услуга не найдена у этого мастера.']);
        }

        $start = Carbon::parse($validated['start_time']);
        $end = $start->copy()->addMinutes((int) $service->duration);

        if ($start->isPast()) {
            return back()->withErrors(['start_time' => 'Нельзя записаться на прошедшее время.']);
        }

        $overlap = Appointment::where('master_id', $master->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();

        if ($overlap) {
            return back()->withErrors(['start_time' => 'Это время уже занято. Выберите другое.']);
        }

        $appointment = Appointment::create([
            'user_id' => $master->id,
            'master_id' => $master->id,
            'client_id' => $card->id,
            'client_user_id' => $client->id,
            'service_id' => $service->id,
            'service_ids' => [$service->id],
            'client_name' => $card->name,
            'client_phone' => $client->phone,
            'client_email' => $client->email,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'planned',
            'notes' => $validated['notes'] ?? null,
            'total_price' => $service->price,
            'total_duration' => (int) $service->duration,
            'is_fixed_price' => true,
        ]);

        // Push в очередь — не блокирует ответ
        SendPushNotification::dispatch($master, [
            'title' => '📅 Новая запись',
            'body'  => $card->name . ' · ' . $start->format('d.m H:i') . ' · ' . $service->name,
            'icon'  => '/icons/icon-192.png',
            'url'   => '/appointments',
        ]);

        SendPushNotification::dispatch($client, [
            'title' => '✅ Вы записаны',
            'body'  => $master->name . ' · ' . $start->format('d.m H:i') . ' · ' . $service->name,
            'icon'  => '/icons/icon-192.png',
            'url'   => '/me/appointments',
        ]);

        return redirect()->route('me.index')
            ->with('success', 'Вы записаны к мастеру ' . $master->name . ' на ' . $start->format('d.m.Y H:i') . '.');
    }

    public function cancel(Appointment $appointment)
    {
        $client = Auth::user();

        if ((int) $appointment->client_user_id !== (int) $client->id) {
            abort(403);
        }

        if ($appointment->status !== 'planned') {
            return back()->with('error', 'Эту запись нельзя отменить — статус: ' . $appointment->status . '.');
        }

        if ($appointment->start_time->lt(now()->addHours(12))) {
            return back()->with('error', 'Отмена возможна не позднее чем за 12 часов до приёма.');
        }

        $appointment->update(['status' => 'cancelled']);
        $appointment->syncTransaction();

        if ($appointment->master) {
            SendPushNotification::dispatch($appointment->master, [
                'title' => '❌ Клиент отменил запись',
                'body'  => $appointment->client_name . ' · ' . $appointment->start_time->format('d.m H:i'),
                'icon'  => '/icons/icon-192.png',
                'url'   => '/appointments',
            ]);
        }

        return redirect()->route('me.appointments')
            ->with('success', 'Запись на ' . $appointment->start_time->format('d.m.Y H:i') . ' отменена.');
    }
}
