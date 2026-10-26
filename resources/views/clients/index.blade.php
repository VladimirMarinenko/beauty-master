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
        {{-- Поиск --}}
        <form method="GET" action="{{ route('clients.index') }}" class="mb-4" id="clients-search-form">
            <div class="flex gap-2 items-center">
                <input
                        type="search"
                        name="search"
                        id="clients-search-input"
                        value="{{ $search }}"
                        placeholder="Имя, контакт, телефон или соц. сети"
                        class="input-field flex-1"
                        autocomplete="off"
                >
                <button type="button" id="clients-search-reset"
                        class="text-sm font-medium {{ $search !== '' ? '' : 'hidden' }}"
                        style="color: var(--muted); white-space: nowrap; background: none; border: none; cursor: pointer;">
                    Сбросить
                </button>
            </div>
        </form>

        @if(session('success'))
            <div class="mb-4 text-green-600 text-sm font-medium bg-green-50 px-4 py-3 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        <div id="clients-list-container" style="transition: opacity 0.15s;">
            @include('clients._list')
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('clients-search-form');
                const input = document.getElementById('clients-search-input');
                const container = document.getElementById('clients-list-container');
                const resetBtn = document.getElementById('clients-search-reset');

                if (!form || !input || !container) return;

                let timer = null;
                let abortCtrl = null;

                function updateResetVisibility() {
                    if (!resetBtn) return;
                    if (input.value.trim() !== '') {
                        resetBtn.classList.remove('hidden');
                    } else {
                        resetBtn.classList.add('hidden');
                    }
                }

                function updateUrl(q) {
                    const url = new URL(window.location.href);
                    if (q) {
                        url.searchParams.set('search', q);
                    } else {
                        url.searchParams.delete('search');
                    }
                    history.replaceState({}, '', url.toString());
                }

                async function runSearch() {
                    const q = input.value.trim();

                    if (abortCtrl) abortCtrl.abort();
                    abortCtrl = new AbortController();

                    container.style.opacity = '0.5';

                    try {
                        const res = await fetch(
                            '{{ route('clients.live') }}?search=' + encodeURIComponent(q),
                            {
                                headers: {
                                    'Accept': 'text/html',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin',
                                signal: abortCtrl.signal
                            }
                        );

                        if (!res.ok) return;

                        const html = await res.text();
                        container.innerHTML = html;
                        updateUrl(q);
                        updateResetVisibility();
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            console.error('Client search:', err);
                        }
                    } finally {
                        container.style.opacity = '1';
                    }
                }

                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(runSearch, 300);
                    updateResetVisibility();
                });

                // Enter тоже запускает поиск (без перезагрузки)
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    clearTimeout(timer);
                    runSearch();
                });

                resetBtn?.addEventListener('click', function () {
                    input.value = '';
                    clearTimeout(timer);
                    runSearch();
                });

                updateResetVisibility();
            });
        </script>
    @endpush
</x-app-layout>
