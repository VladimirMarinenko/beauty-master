<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $search = $this->normalizeSearch($request);
        $clients = $this->getFilteredClients($search);

        return view('clients.index', compact('clients', 'search'));
    }

    /**
     * AJAX-поиск на странице клиентов — возвращает готовый HTML partial.
     */
    public function live(Request $request)
    {
        $search = $this->normalizeSearch($request);
        $clients = $this->getFilteredClients($search);

        return view('clients._list', compact('clients', 'search'));
    }

    /**
     * JSON-поиск для формы записи (dropdown клиентов).
     */
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

        $clients = Auth::user()->clients()
            ->with('services')
            ->where(function ($qq) use ($q, $isPhone, $phoneDigits) {
                if ($isPhone) {
                    $variants = Client::phoneSearchVariants($phoneDigits);
                    $qq->where(function ($inner) use ($variants) {
                        foreach ($variants as $v) {
                            $inner->orWhere('phone_normalized', 'like', "%{$v}%");
                        }
                    });
                } else {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('contact', 'like', "%{$q}%");
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
            'contact' => 'nullable|string|max:255',
            'is_permanent' => 'nullable|boolean',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
        ]);

        $isPermanent = $request->boolean('is_permanent');

        $client = Auth::user()->clients()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'is_permanent' => $isPermanent,
        ]);

        if ($isPermanent && !empty($validated['service_ids'])) {
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
            'contact' => 'nullable|string|max:255',
            'is_permanent' => 'nullable|boolean',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
        ]);

        $isPermanent = $request->boolean('is_permanent');

        $client->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'is_permanent' => $isPermanent,
        ]);

        if ($isPermanent && !empty($validated['service_ids'])) {
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
        return Auth::user()->clients()
            ->with('services')
            ->when($search !== '', function ($query) use ($search) {
                $isPhone = Client::looksLikePhone($search);
                $phoneDigits = $isPhone ? Client::normalizePhone($search) : null;

                $query->where(function ($q) use ($search, $isPhone, $phoneDigits) {
                    if ($isPhone) {
                        $variants = Client::phoneSearchVariants($phoneDigits);
                        $q->where(function ($inner) use ($variants) {
                            foreach ($variants as $v) {
                                $inner->orWhere('phone_normalized', 'like', "%{$v}%");
                            }
                        });
                    } else {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('contact', 'like', "%{$search}%");
                    }
                });
            })
            ->orderByDesc('is_permanent')
            ->orderBy('name')
            ->get();
    }

    private function authorizeClient(Client $client): void
    {
        if ($client->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
