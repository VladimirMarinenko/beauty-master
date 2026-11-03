<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Support\AlphabeticalOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function index()
    {
        $services = AlphabeticalOrder::byName(Auth::user()->services()->get());
        return view('services.index', compact('services'));
    }

    public function create()
    {
        return view('services.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        Auth::user()->services()->create($validated);

        return redirect()->route('services.index')
            ->with('success', 'Услуга добавлена!');
    }

    public function edit(Service $service)
    {
        $this->authorize('update', $service);
        return view('services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $service->update($validated);

        return redirect()->route('services.index')
            ->with('success', 'Услуга обновлена!');
    }

    public function destroy(Service $service)
    {
        $this->authorize('delete', $service);
        $service->delete();

        return redirect()->route('services.index')
            ->with('success', 'Услуга удалена!');
    }
}
