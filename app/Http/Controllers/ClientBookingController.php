<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentWriteLock;
use App\Services\BookingAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientBookingController extends Controller
{
    public function show(User $master)
    {
        $client = Auth::user();

        if (! $client->isClient() || $master->role !== User::ROLE_MASTER) {
            abort(404);
        }

        $card = $client->myClientCards()->where('master_id', $master->id)->first();
        if (! $card) {
            abort(403, 'Вы не привязаны к этому мастеру.');
        }

        $services = $master->services()->orderBy('name')->get();

        return view('me.book', compact('master', 'card', 'services'));
    }

    public function slots(Request $request, User $master)
    {
        $client = Auth::user();

        if (! $client->isClient() || $master->role !== User::ROLE_MASTER) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $card = $client->myClientCards()->where('master_id', $master->id)->first();
        if (! $card) {
            return response()->json(['error' => 'Not attached'], 403);
        }

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = $master->services()->where('id', $validated['service_id'])->first();
        if (! $service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        return response()->json(['slots' => app(BookingAvailability::class)->slots(
            $master,
            Carbon::parse($validated['date']),
            (int) $service->duration
        )]);
    }

    public function availability(Request $request, User $master)
    {
        $client = Auth::user();

        if (! $client->isClient() || $master->role !== User::ROLE_MASTER) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if (! $client->myClientCards()->where('master_id', $master->id)->exists()) {
            return response()->json(['error' => 'Not attached'], 403);
        }

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m', 'regex:/^(?!0000)[0-9]{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $service = $master->services()->where('id', $validated['service_id'])->first();
        if (! $service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        return response()->json(['days' => app(BookingAvailability::class)->days(
            $master,
            Carbon::createFromFormat('!Y-m', $validated['month']),
            (int) $service->duration
        )]);
    }

    public function book(Request $request, User $master)
    {
        return app(AppointmentWriteLock::class)->run($master->id, function () use ($request, $master) {
            $master->refresh();

            return $this->bookLocked($request, $master);
        });
    }

    private function bookLocked(Request $request, User $master)
    {
        $client = Auth::user();

        if (! $client->isClient() || $master->role !== User::ROLE_MASTER) {
            abort(404);
        }

        $card = $client->myClientCards()->where('master_id', $master->id)->first();
        if (! $card) {
            abort(403, 'Вы не привязаны к этому мастеру.');
        }

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'start_time' => ['required', 'date_format:Y-m-d H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = $master->services()->where('id', $validated['service_id'])->first();
        if (! $service) {
            return back()->withErrors(['service_id' => 'Услуга не найдена у этого мастера.']);
        }

        $start = Carbon::parse($validated['start_time']);
        $end = $start->copy()->addMinutes((int) $service->duration);

        if ($start->isPast()) {
            return back()->withErrors(['start_time' => 'Нельзя записаться на прошедшее время.']);
        }

        $available = app(BookingAvailability::class)->slots($master, $start->copy(), (int) $service->duration);
        if (! in_array($start->format('Y-m-d H:i'), array_column($available, 'start'), true)) {
            return back()->withErrors(['start_time' => 'Это время недоступно. Выберите свободное время заново.'])->withInput();
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

        DB::afterCommit(function () use ($master, $client, $card, $start, $service) {
            try {
                // Push в очередь — не блокирует ответ
                SendPushNotification::dispatch($master, [
                    'title' => '📅 Новая запись',
                    'body' => $card->name.' · '.$start->format('d.m H:i').' · '.$service->name,
                    'icon' => '/icons/icon-192.png',
                    'url' => '/appointments',
                ]);

                SendPushNotification::dispatch($client, [
                    'title' => '✅ Вы записаны',
                    'body' => $master->name.' · '.$start->format('d.m H:i').' · '.$service->name,
                    'icon' => '/icons/icon-192.png',
                    'url' => '/me/appointments',
                ]);

            } catch (\Throwable $e) {
                report($e);
            }
        });

        return redirect()->route('me.index')
            ->with('success', 'Вы записаны к мастеру '.$master->name.' на '.$start->format('d.m.Y H:i').'.');
    }

    public function cancel(Appointment $appointment)
    {
        return app(AppointmentWriteLock::class)->run((int) $appointment->master_id, function () use ($appointment) {
            $appointment->refresh();

            return $this->cancelLocked($appointment);
        });
    }

    private function cancelLocked(Appointment $appointment)
    {
        $client = Auth::user();

        if ((int) $appointment->client_user_id !== (int) $client->id) {
            abort(403);
        }

        if ($appointment->status !== 'planned') {
            return back()->with('error', 'Эту запись нельзя отменить — статус: '.$appointment->status.'.');
        }

        if ($appointment->start_time->lt(now()->addHours(12))) {
            return back()->with('error', 'Отмена возможна не позднее чем за 12 часов до приёма.');
        }

        $appointment->update(['status' => 'cancelled']);
        $appointment->syncTransaction();

        DB::afterCommit(function () use ($appointment) {
            try {
                if ($appointment->master) {
                    SendPushNotification::dispatch($appointment->master, [
                        'title' => '❌ Клиент отменил запись',
                        'body' => $appointment->client_name.' · '.$appointment->start_time->format('d.m H:i'),
                        'icon' => '/icons/icon-192.png',
                        'url' => '/appointments',
                    ]);
                }

            } catch (\Throwable $e) {
                report($e);
            }
        });

        return redirect()->route('me.appointments')
            ->with('success', 'Запись на '.$appointment->start_time->format('d.m.Y H:i').' отменена.');
    }
}
