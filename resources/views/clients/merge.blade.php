<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Слияние клиентов</h2>
            <a href="{{ route('clients.edit', $client) }}" class="text-gray-500">← Назад</a>
        </div>
    </x-slot>

    <div class="app-container">
        @if($errors->any())
            <div class="mb-4 text-red-600 text-sm font-medium bg-red-50 px-4 py-3 rounded-xl">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Текущая карточка --}}
        <div class="card p-4 mb-4">
            <p class="text-xs font-semibold mb-2" style="color: var(--muted);">ТЕКУЩАЯ КАРТОЧКА</p>
            <div class="font-semibold text-base" style="color: var(--text);">{{ $client->name }}</div>
            @if($client->phone)
                <div class="text-sm mt-1" style="color: var(--muted);">📞 {{ $client->phone }}</div>
            @endif
            @if($client->phone2)
                <div class="text-sm" style="color: var(--muted);">📞 {{ $client->phone2 }}</div>
            @endif
            @if($client->contact)
                <div class="text-sm" style="color: var(--muted);">✉️ {{ $client->contact }}</div>
            @endif
            @if($client->client_user_id)
                <div class="text-xs mt-2" style="color: #10b981;">✓ Зарегистрирован в системе</div>
            @else
                <div class="text-xs mt-2" style="color: var(--muted);">Без аккаунта</div>
            @endif
        </div>

        <p class="text-sm mb-3" style="color: var(--muted);">
            Найдите вторую карточку того же человека и выберите, какая останется.
            Все записи перепривяжутся, вторая карточка удалится.
        </p>

        {{-- Поиск второй карточки --}}
        <form method="GET" action="{{ route('clients.merge', $client) }}" class="mb-4">
            <input type="search" name="search" value="{{ $search }}"
                   class="input-field" placeholder="Поиск: имя, телефон, соц. сети"
                   autocomplete="off">
        </form>

        @if($candidates->isEmpty())
            <div class="card p-6 text-center">
                <p style="color: var(--muted);">
                    @if($search !== '')
                        По запросу «{{ $search }}» ничего не найдено.
                    @else
                        Других клиентов нет.
                    @endif
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('clients.merge.store', $client) }}" id="merge-form">
                @csrf

                <div class="space-y-3 mb-4">
                    @foreach($candidates as $candidate)
                        <label class="card p-4 flex items-start gap-3 cursor-pointer" style="display: block;">
                            <div class="flex items-start gap-3">
                                <input type="radio" name="merge_with_id" value="{{ $candidate->id }}"
                                       class="merge-radio mt-1" data-name="{{ $candidate->name }}">
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-sm" style="color: var(--text);">{{ $candidate->name }}</div>
                                    @if($candidate->phone)
                                        <div class="text-xs mt-0.5" style="color: var(--muted);">📞 {{ $candidate->phone }}</div>
                                    @endif
                                    @if($candidate->phone2)
                                        <div class="text-xs" style="color: var(--muted);">📞 {{ $candidate->phone2 }}</div>
                                    @endif
                                    @if($candidate->contact)
                                        <div class="text-xs" style="color: var(--muted);">✉️ {{ $candidate->contact }}</div>
                                    @endif
                                    @if($candidate->client_user_id)
                                        <div class="text-xs mt-1" style="color: #10b981;">✓ Зарегистрирован</div>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                {{-- Выбор главной карточки --}}
                <div id="keep-block" class="card p-4 mb-4 hidden">
                    <p class="text-sm font-semibold mb-2" style="color: var(--text);">Какая карточка останется?</p>
                    <label class="flex items-center gap-2 mb-2 cursor-pointer">
                        <input type="radio" name="keep_id" value="{{ $client->id }}" checked>
                        <span class="text-sm">Оставить <strong>{{ $client->name }}</strong> (текущая)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="keep_id" value="" id="keep-other-radio" disabled>
                        <span class="text-sm">Оставить <strong id="keep-other-name">выбранную</strong></span>
                    </label>
                    <p class="text-xs mt-2" style="color: var(--muted);">
                        Имя, телефон и другие поля возьмутся из оставленной карточки. Пустые поля дополнятся из второй.
                    </p>
                </div>

                <button type="submit" id="merge-submit" class="btn-primary hidden"
                        onclick="return confirm('Объединить клиентов? Вторая карточка будет удалена.');">
                    Объединить
                </button>
            </form>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('merge-form');
                if (!form) return;

                const keepBlock = document.getElementById('keep-block');
                const keepOtherRadio = document.getElementById('keep-other-radio');
                const keepOtherName = document.getElementById('keep-other-name');
                const submitBtn = document.getElementById('merge-submit');

                form.querySelectorAll('.merge-radio').forEach(radio => {
                    radio.addEventListener('change', function () {
                        keepBlock.classList.remove('hidden');
                        submitBtn.classList.remove('hidden');
                        keepOtherRadio.value = radio.value;
                        keepOtherRadio.disabled = false;
                        keepOtherName.textContent = radio.dataset.name;
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
