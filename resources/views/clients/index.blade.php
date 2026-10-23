<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">{{ __('Клиенты') }}</h2>
            <a href="{{ route('clients.create') }}" class="btn-primary" style="width: auto; padding: 10px 16px; font-size: 14px;">
                + Добавить
            </a>
        </div>
    </x-slot>

    <div class="app-container">
        {{-- Форма поиска --}}
        <form method="GET" action="{{ route('clients.index') }}" class="mb-4">
            <div class="flex gap-2 items-center">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Имя, контакта,телефон или соц. сети"
                    class="input-field flex-1"
                >
                <button type="submit" class="btn-primary" style="width: auto; padding: 10px 16px;">
                    Найти
                </button>
                @if($search !== '')
                    <a href="{{ route('clients.index') }}" class="text-sm font-medium" style="color: var(--muted); white-space: nowrap;">
                        Сбросить
                    </a>
                @endif
            </div>
        </form>

        @if(session('success'))
            <div class="mb-4 text-green-600 text-sm font-medium bg-green-50 px-4 py-3 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        @if($clients->count() > 0)
            <div class="space-y-3">
                @foreach($clients as $client)
                    <div class="card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-semibold text-base" style="color: var(--text);">
                                        {{ $client->name }}
                                    </h4>
                                    @if($client->is_permanent)
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-full" style="background: #eef2ff; color: var(--primary);">
                                            постоянный
                                        </span>
                                    @endif
                                </div>

                                @if($client->phone)
                                    <p class="text-sm mt-1" style="color: var(--muted);">
                                        📞 {{ $client->phone }}
                                    </p>
                                @endif

                                @if($client->contact)
                                    <p class="text-sm mt-0.5" style="color: var(--muted);">
                                        ✉️ {{ $client->contact }}
                                    </p>
                                @endif

                                @if($client->is_permanent && $client->services->count() > 0)
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($client->services as $service)
                                            <span class="text-xs px-2 py-0.5 rounded-full" style="background: var(--bg); color: var(--muted); border: 1px solid var(--border);">
                                                {{ $service->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col items-end space-y-2 ml-2 flex-shrink-0">
                                <a href="{{ route('clients.edit', $client) }}" class="text-sm font-semibold" style="color: var(--primary);">
                                    Изменить
                                </a>
                                <form action="{{ route('clients.destroy', $client) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-500" onclick="return confirm('Удалить клиента?')">
                                        Удалить
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        @elseif($search !== '')
            {{-- Поиск ничего не нашёл — это НЕ «клиентов нет» --}}
            <div class="card p-8 text-center">
                <div class="text-4xl mb-3">🔍</div>
                <p class="font-medium" style="color: var(--muted);">
                    По запросу «{{ $search }}» ничего не найдено.
                </p>
                <a href="{{ route('clients.index') }}" class="btn-primary mt-4" style="display: inline-block; width: auto;">
                    Сбросить поиск
                </a>
            </div>

        @else
            {{-- Клиентов действительно нет --}}
            <div class="card p-8 text-center">
                <div class="text-4xl mb-3">👥</div>
                <p class="font-medium" style="color: var(--muted);">Клиентов пока нет.</p>
                <a href="{{ route('clients.create') }}" class="btn-primary mt-4" style="display: inline-block; width: auto;">
                    Добавить первого клиента
                </a>
            </div>
        @endif
    </div>
</x-app-layout>
