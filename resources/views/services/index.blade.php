<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ __('Мои услуги') }}
            </h2>
            <a href="{{ route('services.create') }}" class="btn-primary" style="width: auto; padding: 10px 16px; font-size: 14px;">
                + Добавить
            </a>
        </div>
    </x-slot>

    <div class="app-container">
        @if(session('success'))
            <div class="mb-4 text-green-600 text-sm font-medium bg-green-50 px-4 py-3 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        @if($services->count() > 0)
            <div class="space-y-3">
                @foreach($services as $service)
                    <div class="card p-4 flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="font-semibold text-base" style="color: var(--text);">{{ $service->name }}</h4>
                            <p class="text-sm mt-1" style="color: var(--muted);">
                                {{ number_format($service->price, 2) }} ₽ · {{ $service->duration }} мин.
                            </p>
                            @if($service->description)
                                <p class="text-xs mt-2" style="color: var(--muted);">{{ $service->description }}</p>
                            @endif
                        </div>
                        <div class="flex flex-col items-end space-y-3 ml-4">
                            <a href="{{ route('services.edit', $service) }}" class="text-sm font-semibold" style="color: var(--primary);">
                                Изменить
                            </a>
                            <form action="{{ route('services.destroy', $service) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-500" onclick="return confirm('Удалить услугу?')">
                                    Удалить
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card p-8 text-center">
                <div class="text-4xl mb-3">💆‍♀️</div>
                <p class="font-medium" style="color: var(--muted);">У вас пока нет услуг.</p>
                <a href="{{ route('services.create') }}" class="btn-primary mt-4" style="display: inline-block; width: auto;">
                    Добавить первую услугу
                </a>
            </div>
        @endif
    </div>
</x-app-layout>
