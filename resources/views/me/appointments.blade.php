<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Мои записи</h2>
            <a href="{{ route('me.index') }}" class="text-gray-500">← Назад</a>
        </div>
    </x-slot>

    <div class="app-container">
        @if(session('success'))
            <div class="mb-4 text-green-600 text-sm font-medium bg-green-50 px-4 py-3 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 text-red-600 text-sm font-medium bg-red-50 px-4 py-3 rounded-xl">
                {{ session('error') }}
            </div>
        @endif

        {{-- Табы --}}
        <div class="flex gap-2 mb-4">
            <a href="{{ route('me.appointments', ['tab' => 'upcoming']) }}"
               class="flex-1 text-center py-2 rounded-xl text-sm font-semibold"
               style="text-decoration: none; {{ $tab === 'upcoming' ? 'background: var(--primary); color: white;' : 'background: var(--bg); color: var(--muted);' }}">
                Предстоящие
            </a>
            <a href="{{ route('me.appointments', ['tab' => 'past']) }}"
               class="flex-1 text-center py-2 rounded-xl text-sm font-semibold"
               style="text-decoration: none; {{ $tab === 'past' ? 'background: var(--primary); color: white;' : 'background: var(--bg); color: var(--muted);' }}">
                Прошедшие
            </a>
            <a href="{{ route('me.appointments', ['tab' => 'cancelled']) }}"
               class="flex-1 text-center py-2 rounded-xl text-sm font-semibold"
               style="text-decoration: none; {{ $tab === 'cancelled' ? 'background: var(--primary); color: white;' : 'background: var(--bg); color: var(--muted);' }}">
                Отменённые
            </a>
        </div>

        @if($appointments->isEmpty())
            <div class="card p-8 text-center">
                <div class="text-4xl mb-3">
                    @if($tab === 'upcoming') 📅
                    @elseif($tab === 'past') 🕓
                    @else 🚫
                    @endif
                </div>
                <p class="font-medium" style="color: var(--muted);">
                    @if($tab === 'upcoming')
                        У вас нет предстоящих записей.
                    @elseif($tab === 'past')
                        Прошедших записей нет.
                    @else
                        Отменённых записей нет.
                    @endif
                </p>
                @if($tab === 'upcoming')
                    <a href="{{ route('me.index') }}" class="btn-primary mt-4" style="display: inline-block; width: auto;">
                        Записаться к мастеру
                    </a>
                @endif
            </div>
        @else
            <div class="space-y-3">
                @foreach($appointments as $appointment)
                    @php
                        $canCancel = $tab === 'upcoming'
                            && $appointment->status === 'planned'
                            && $appointment->start_time->gte(now()->addHours(12));
                        $statusLabel = match($appointment->status) {
                            'planned' => 'Запланирована',
                            'completed' => 'Выполнена',
                            'cancelled' => 'Отменена',
                            'no_show' => 'Не пришёл',
                            default => $appointment->status,
                        };
                        $statusColor = match($appointment->status) {
                            'planned' => '#3b82f6',
                            'completed' => '#10b981',
                            'cancelled' => '#ef4444',
                            'no_show' => '#f59e0b',
                            default => '#6b7280',
                        };
                    @endphp
                    <div class="card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <h4 class="font-semibold text-base" style="color: var(--text);">
                                        {{ $appointment->master?->name ?? 'Мастер' }}
                                    </h4>
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                                          style="background: {{ $statusColor }}20; color: {{ $statusColor }};">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                <p class="text-sm font-medium" style="color: var(--text);">
                                    {{ $appointment->start_time->format('d.m.Y, H:i') }}
                                    — {{ $appointment->end_time->format('H:i') }}
                                </p>
                                @if($appointment->service)
                                    <p class="text-sm mt-1" style="color: var(--muted);">
                                        {{ $appointment->service->name }}
                                    </p>
                                @endif
                                @if($appointment->total_price)
                                    <p class="text-sm mt-0.5" style="color: var(--muted);">
                                        {{ number_format($appointment->total_price, 0, '.', ' ') }} ₽
                                    </p>
                                @endif
                            </div>

                            @if($canCancel)
                                <form method="POST"
                                      action="{{ route('me.appointments.cancel', ['appointment' => $appointment->id]) }}"
                                      class="flex-shrink-0"
                                      onsubmit="return confirm('Отменить запись на {{ $appointment->start_time->format('d.m.Y H:i') }}?');">
                                    @csrf
                                    <button type="submit" class="text-sm font-medium text-red-500">
                                        Отменить
                                    </button>
                                </form>
                            @elseif($tab === 'upcoming' && $appointment->status === 'planned' && $appointment->start_time->lt(now()->addHours(12)))
                                <span class="text-xs flex-shrink-0" style="color: var(--muted); text-align: right; max-width: 100px;">
                                    Отмена недоступна — менее 12 ч
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
