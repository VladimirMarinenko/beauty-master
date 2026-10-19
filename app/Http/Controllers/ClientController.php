<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Auth::user()->clients()
            ->with('services')
            ->orderByDesc('is_permanent')
            ->orderBy('name')
            ->get();

        return view('clients.index', compact('clients'));
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

    private function authorizeClient(Client $client): void
    {
        if ($client->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
