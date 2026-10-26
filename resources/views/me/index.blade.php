<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Мои мастера</h2>
    </x-slot>

    <div class="app-container">
        <div class="card p-4 mb-4">
            <p style="color: var(--muted);">
                Привет, {{ Auth::user()->name }}! Это ваш личный кабинет.
            </p>
        </div>

        @if($masterCards->isEmpty())
            <div class="card p-8 text-center">
                <p style="color: var(--muted);">
                    Пока ни к одному мастеру не привязан.
                    Попросите у мастера ссылку на запись.
                </p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($masterCards as $card)
                    <div class="card p-4">
                        <div class="font-semibold" style="color: var(--text);">
                            {{ $card->master->name }}
                        </div>
                        <div class="text-xs mt-1" style="color: var(--muted);">
                            У мастера вы записаны как: {{ $card->name }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
