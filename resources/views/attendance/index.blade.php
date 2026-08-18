<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ __('Подтверждение записей') }}
            </h2>
        </div>
    </x-slot>

    <div class="app-container">
        <div class="card p-5">
            <p class="text-lg font-semibold mb-2" style="color: var(--text);">Здравствуйте, {{ Auth::user()->name }}!</p>
            <p class="text-sm mb-4" style="color: var(--muted);">
                У вас за прошедшее время были записаны
                {{ $appointments->count() }} {{ trans_choice('клиент|клиента|клиентов', $appointments->count()) }}.
            </p>

            @if($step === 'all')
                <form method="POST" action="{{ route('attendance.process') }}">
                    @csrf
                    <input type="hidden" name="action" value="all_arrived">
                    <input type="hidden" name="appointment_ids" value="{{ $appointments->pluck('id')->implode(',') }}">
                    <button type="submit" class="btn-primary" style="background: #10b981; --primary: #10b981; --primary-dark: #059669;">
                        Да, все пришли
                    </button>
                </form>
                <a href="{{ route('attendance.index', ['step' => 'individual']) }}"
                   class="btn-secondary mt-3" style="display: block;">
                    Нет, не все
                </a>
            @else
                <form method="POST" action="{{ route('attendance.process') }}">
                    @csrf
                    <input type="hidden" name="action" value="individual">
                    <input type="hidden" name="appointment_ids" value="{{ $appointments->pluck('id')->implode(',') }}">
                    <div class="space-y-3 mb-5">
                        @foreach($appointments as $appointment)
                            <div class="bg-gray-50 rounded-xl p-4">
                                <p class="font-semibold" style="color: var(--text);">{{ $appointment->client_name }}</p>
                                <p class="text-sm mt-1" style="color: var(--muted);">
                                    {{ $appointment->service->name }} —
                                    {{ $appointment->start_time->format('d.m.Y H:i') }}
                                </p>
                                <div class="mt-3 flex gap-4">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="statuses[{{ $appointment->id }}]" value="arrived" class="mr-2 h-4 w-4 text-green-600">
                                        <span class="text-sm font-medium" style="color: var(--text);">Пришёл</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="statuses[{{ $appointment->id }}]" value="no_show" class="mr-2 h-4 w-4 text-red-600">
                                        <span class="text-sm font-medium" style="color: var(--text);">Не пришёл</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" class="btn-primary">
                        Сохранить
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
