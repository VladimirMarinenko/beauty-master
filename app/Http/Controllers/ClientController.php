<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Services\AppointmentWriteLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $search = $this->normalizeSearch($request);
        $clients = $this->getFilteredClients($search);

        $newAttachedQuery = Auth::user()->clientCards()
            ->whereNotNull('client_user_id')
            ->whereNull('notified_at');

        $newAttachedCount = (clone $newAttachedQuery)->count();

        $newAttachedNames = (clone $newAttachedQuery)
            ->orderByDesc('id')
            ->limit(5)
            ->pluck('name');

        return view('clients.index', compact(
            'clients',
            'search',
            'newAttachedCount',
            'newAttachedNames'
        ));
    }

    public function live(Request $request)
    {
        $search = $this->normalizeSearch($request);
        $clients = $this->getFilteredClients($search);

        return view('clients._list', compact('clients', 'search'));
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $q = preg_replace('/[\p{Z}\s]+/u', ' ', $q);
        $q = mb_substr($q, 0, 100);

        $isPhone = Client::looksLikePhone($q);
        $phoneDigits = $isPhone ? Client::normalizePhone($q) : null;

        $clients = Auth::user()->clientCards()
            ->with('services')
            ->where(function ($qq) use ($q, $isPhone, $phoneDigits) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%");

                if ($isPhone && $phoneDigits) {
                    $variants = Client::phoneSearchVariants($phoneDigits);
                    foreach ($variants as $v) {
                        $qq->orWhere('phone_normalized', 'like', "%{$v}%")
                            ->orWhere('phone2_normalized', 'like', "%{$v}%");
                    }
                }
            })
            ->orderByDesc('is_permanent')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($clients->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'phone2' => $c->phone2,
                'contact' => $c->contact,
                'is_permanent' => (bool) $c->is_permanent,
                'service_ids' => $c->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        }));
    }

    public function create()
    {
        $services = Auth::user()->services()->orderBy('name')->get();

        return view('clients.create', compact('services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'phone2' => 'nullable|string|max:255',
            'contact' => 'nullable|string|max:255',
            'is_permanent' => 'nullable|boolean',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
        ]);

        $isPermanent = $request->boolean('is_permanent');

        $client = Auth::user()->clientCards()->create([
            'name' => $validated['name'],
            'phone' => $this->cleanPhone($validated['phone'] ?? null),
            'phone2' => $this->cleanPhone($validated['phone2'] ?? null),
            'contact' => $validated['contact'] ?? null,
            'is_permanent' => $isPermanent,
        ]);

        if ($isPermanent && ! empty($validated['service_ids'])) {
            $allowedIds = Auth::user()->services()
                ->whereIn('id', $validated['service_ids'])
                ->pluck('id')
                ->toArray();
            $client->services()->sync($allowedIds);
        }

        return redirect()->route('clients.index')
            ->with('success', 'Клиент добавлен!');
    }

    public function edit(Client $client)
    {
        $this->authorizeClient($client);

        $services = Auth::user()->services()->orderBy('name')->get();
        $selectedServiceIds = $client->services->pluck('id')->toArray();

        return view('clients.edit', compact('client', 'services', 'selectedServiceIds'));
    }

    public function update(Request $request, Client $client)
    {
        $this->authorizeClient($client);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'phone2' => 'nullable|string|max:255',
            'contact' => 'nullable|string|max:255',
            'is_permanent' => 'nullable|boolean',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
        ]);

        $isPermanent = $request->boolean('is_permanent');

        $client->update([
            'name' => $validated['name'],
            'phone' => $this->cleanPhone($validated['phone'] ?? null),
            'phone2' => $this->cleanPhone($validated['phone2'] ?? null),
            'contact' => $validated['contact'] ?? null,
            'is_permanent' => $isPermanent,
        ]);

        if ($isPermanent && ! empty($validated['service_ids'])) {
            $allowedIds = Auth::user()->services()
                ->whereIn('id', $validated['service_ids'])
                ->pluck('id')
                ->toArray();
            $client->services()->sync($allowedIds);
        } else {
            $client->services()->detach();
        }

        return redirect()->route('clients.index')
            ->with('success', 'Клиент обновлён!');
    }

    public function destroy(Client $client)
    {
        $this->authorizeClient($client);
        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Клиент удалён!');
    }

    public function markNotified()
    {
        Auth::user()->clientCards()
            ->whereNotNull('client_user_id')
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        return redirect()->route('clients.index')
            ->with('success', 'Уведомление скрыто.');
    }

    // =========================
    // Helpers
    // =========================

    private function normalizeSearch(Request $request): string
    {
        $search = (string) $request->input('search', '');
        $search = preg_replace('/[\p{Z}\s]+/u', ' ', $search);
        $search = trim($search);

        return mb_substr($search, 0, 100);
    }

    private function getFilteredClients(string $search)
    {
        return Auth::user()->clientCards()
            ->with('services')
            ->when($search !== '', function ($query) use ($search) {
                $isPhone = Client::looksLikePhone($search);
                $phoneDigits = $isPhone ? Client::normalizePhone($search) : null;

                $query->where(function ($q) use ($search, $isPhone, $phoneDigits) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");

                    if ($isPhone && $phoneDigits) {
                        $variants = Client::phoneSearchVariants($phoneDigits);
                        foreach ($variants as $v) {
                            $q->orWhere('phone_normalized', 'like', "%{$v}%")
                                ->orWhere('phone2_normalized', 'like', "%{$v}%");
                        }
                    }
                });
            })
            ->orderByDesc('is_permanent')
            ->orderBy('name')
            ->get();
    }

    /**
     * Очищает телефон: возвращает строку только если в ней есть телефон
     * (минимум 10 цифр после нормализации). Иначе null.
     * Отсекает placeholder "+7 (___) ___-__-__" и прочий мусор.
     */
    private function cleanPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $normalized = Client::normalizePhone($phone);

        return $normalized ? trim($phone) : null;
    }

    public function mergeForm(Client $client, Request $request)
    {
        $this->authorizeClient($client);

        $search = $this->normalizeSearch($request);
        $candidates = $this->getFilteredClients($search)
            ->where('id', '!=', $client->id)
            ->take(50);

        return view('clients.merge', compact('client', 'candidates', 'search'));
    }

    public function merge(Request $request, Client $client)
    {
        return app(AppointmentWriteLock::class)->run(Auth::id(), function () use ($request, $client) {
            $client->refresh();

            return $this->mergeLocked($request, $client);
        });
    }

    private function mergeLocked(Request $request, Client $client)
    {
        $this->authorizeClient($client);

        $validated = $request->validate([
            'merge_with_id' => 'required|integer|exists:clients,id',
            'keep_id' => 'required|integer',
        ]);

        $other = Client::findOrFail($validated['merge_with_id']);
        $this->authorizeClient($other);

        if ((int) $other->id === (int) $client->id) {
            return back()->withErrors(['merge_with_id' => 'Нельзя слить клиента с самим собой.']);
        }

        $keepId = (int) $validated['keep_id'];
        $clientId = (int) $client->id;
        $otherId = (int) $other->id;

        if (! in_array($keepId, [$clientId, $otherId], true)) {
            return back()->withErrors(['keep_id' => 'Некорректный выбор основной карточки.']);
        }

        $keep = $keepId === $clientId ? $client : $other;
        $drop = $keepId === $clientId ? $other : $client;

        if ((int) $keep->id === (int) $drop->id) {
            return back()->withErrors(['merge_with_id' => 'Карточки совпадают — нечего сливать.']);
        }

        if ($keep->client_user_id && $drop->client_user_id && (int) $keep->client_user_id !== (int) $drop->client_user_id) {
            return back()->withErrors(['merge_with_id' => 'Нельзя объединять карточки разных аккаунтов клиентов.']);
        }

        $lostPhonesCount = 0;
        $contactsCount = 0;

        DB::transaction(function () use ($keep, $drop, &$lostPhonesCount, &$contactsCount) {
            // 1. Объединяем телефоны — до 2 разных.
            //    cleanPhone отсекает placeholder "+7 (___) ___-__-__",
            //    пустые строки и прочий мусор (меньше 10 цифр).
            //    Порядок приоритета:
            //      1) keep.phone   — основной главного
            //      2) drop.phone   — основной вливаемого
            //      3) keep.phone2  — дополнительный главного
            //      4) drop.phone2  — дополнительный вливаемого
            $allPhones = [];
            foreach ([
                         $this->cleanPhone($keep->phone),
                         $this->cleanPhone($drop->phone),
                         $this->cleanPhone($keep->phone2),
                         $this->cleanPhone($drop->phone2),
                     ] as $p) {
                if ($p !== null && ! in_array($p, $allPhones, true)) {
                    $allPhones[] = $p;
                }
            }

            $keep->phone = $allPhones[0] ?? null;
            $keep->phone2 = $allPhones[1] ?? null;
            $lostPhonesCount = max(0, count($allPhones) - 2);

            // 2. Объединяем contact через "; "
            $contacts = collect([$keep->contact, $drop->contact])
                ->map(fn ($c) => $c ? trim((string) $c) : null)
                ->filter()
                ->unique()
                ->values();

            $keep->contact = $contacts->isEmpty() ? null : $contacts->implode('; ');
            $contactsCount = $contacts->count();

            // 2.1. client_user_id: если у главной нет, но у удаляемой есть — переносим.
            //      Должно быть ДО блока notified_at, иначе notified_at не выставится
            //      после переноса client_user_id.
            if (! $keep->client_user_id && $drop->client_user_id) {
                $keep->client_user_id = $drop->client_user_id;
            }

            // 3. Постоянный клиент
            if ($drop->is_permanent) {
                $keep->is_permanent = true;
            }

            // 4. notified_at — если есть client_user_id, значит "увидели"
            if ($keep->client_user_id) {
                $keep->notified_at = now();
            }

            $keep->save();

            // 5. Перепривязка записей ДО удаления.
            //    client_user_id берём из $keep (уже с перенесённым значением).
            Appointment::where('client_id', $drop->id)
                ->update([
                    'client_id' => $keep->id,
                    'client_user_id' => $keep->client_user_id,
                ]);

            // 6. Объединение услуг
            $keepServiceIds = $keep->services()->pluck('services.id')->toArray();
            $dropServiceIds = $drop->services()->pluck('services.id')->toArray();
            $allServiceIds = array_values(array_unique(array_merge($keepServiceIds, $dropServiceIds)));
            if (! empty($allServiceIds)) {
                $keep->services()->sync($allServiceIds);
            }

            // 7. Удаляем вторую карточку
            $drop->services()->detach();
            $drop->delete();
        });

        $message = 'Клиенты объединены. Осталась карточка «'.$keep->name.'».';

        if ($lostPhonesCount > 0) {
            $message .= ' Внимание: было '.($lostPhonesCount + 2).' телефонов, сохранились только 2 (основной + дополнительный). Проверьте карточку вручную.';
        }

        if ($contactsCount > 1) {
            $message .= ' Контакты объединены через «; ».';
        }

        return redirect()->route('clients.edit', $keep)
            ->with('success', $message);
    }

    private function authorizeClient(Client $client): void
    {
        if ((int) $client->master_id !== (int) Auth::id()) {
            abort(403);
        }
    }
}
