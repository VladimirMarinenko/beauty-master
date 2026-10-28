<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Мои мастера</h2>
    </x-slot>

    <div class="app-container">
        {{-- Push-блок: показывается JS только если нужно --}}
        <div class="card mb-4" id="me-push-block"
             style="display: none; padding: 0; overflow: hidden; border-color: var(--primary-light);">
            <div style="display: flex; align-items: flex-start; gap: 12px; padding: 16px;">
                <div style="
                    width: 40px; height: 40px; flex-shrink: 0;
                    border-radius: 12px;
                    background: #eef2ff; color: var(--primary);
                    display: flex; align-items: center; justify-content: center;
                    font-size: 20px;
                ">🔔</div>
                <div style="flex: 1; min-width: 0;">
                    <p class="text-sm font-semibold" style="color: var(--text);">
                        Включите уведомления
                    </p>
                    <p class="text-xs mt-1" style="color: var(--muted); line-height: 1.4;">
                        Получайте уведомления о записи и напоминания за 2 часа до приёма.
                    </p>
                    <div id="me-push-actions" style="margin-top: 10px;">
                        <button type="button" id="me-push-btn"
                                class="btn-primary"
                                style="width: auto; padding: 8px 14px; font-size: 13px;">
                            Включить
                        </button>
                    </div>
                    <p id="me-push-error" class="text-xs mt-2 hidden" style="color: #dc2626;"></p>
                </div>
            </div>
        </div>

        {{-- Push-успех: краткое сообщение после подписки --}}
        <div id="me-push-success" class="mb-4 hidden"
             style="padding: 10px 16px; border-radius: 12px; background: #ecfdf5; color: #059669; font-size: 13px;">
            ✓ Уведомления включены
        </div>

        <div class="card p-4 mb-4">
            <p style="color: var(--muted);">
                Привет, {{ Auth::user()->name }}! Это ваш личный кабинет.
            </p>
        </div>

        <div class="grid grid-cols-4 gap-3 mb-4">
            <a href="{{ route('me.appointments') }}" class="card p-4 text-center" style="text-decoration: none;">
                <div class="text-2xl mb-1">📅</div>
                <div class="text-sm font-semibold" style="color: var(--text);">Мои записи</div>
                <div class="text-xs mt-1" style="color: var(--muted);">История и предстоящие</div>
            </a>
        </div>

        <h3 class="text-base font-semibold mb-3" style="color: var(--text);">Мои мастера</h3>

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
                        <div class="mt-3">
                            <a href="{{ route('me.book', ['master' => $card->master_id]) }}"
                               class="btn-primary"
                               style="display: inline-block; width: auto; padding: 10px 16px; font-size: 14px;">
                                Записаться
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            (function () {
                const VAPID_PUBLIC_KEY = '{{ config('webpush.vapid.public_key') }}';

                const block = document.getElementById('me-push-block');
                const btn = document.getElementById('me-push-btn');
                const errorEl = document.getElementById('me-push-error');
                const successEl = document.getElementById('me-push-success');
                const actionsEl = document.getElementById('me-push-actions');

                if (!block || !btn) return;

                function urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
                    const raw = window.atob(base64);
                    const out = new Uint8Array(raw.length);
                    for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
                    return out;
                }

                function setButtonState(state) {
                    if (state === 'loading') {
                        btn.disabled = true;
                        btn.textContent = 'Запрос...';
                        btn.style.opacity = '0.6';
                    } else if (state === 'retry') {
                        btn.disabled = false;
                        btn.textContent = 'Попробовать снова';
                        btn.style.opacity = '1';
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Включить';
                        btn.style.opacity = '1';
                    }
                }

                function showError(msg) {
                    errorEl.textContent = msg;
                    errorEl.classList.remove('hidden');
                }

                function hideError() {
                    errorEl.classList.add('hidden');
                }

                async function subscribe() {
                    try {
                        setButtonState('loading');
                        hideError();

                        // 1. Разрешение
                        const perm = await Notification.requestPermission();
                        if (perm !== 'granted') {
                            setButtonState('retry');
                            showError('Разрешение не получено. Разрешите уведомления в настройках браузера (иконка замка рядом с адресом).');
                            return;
                        }

                        // 2. SW
                        const reg = await navigator.serviceWorker.ready;

                        // 3. Подписка
                        if (!VAPID_PUBLIC_KEY || VAPID_PUBLIC_KEY.length < 80) {
                            throw new Error('VAPID-ключ не настроен на сервере');
                        }

                        const subscription = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
                        });

                        // 4. Сохранить на сервере
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                        const res = await fetch('/push-subscribe', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(subscription),
                        });

                        if (!res.ok) {
                            throw new Error('HTTP ' + res.status);
                        }

                        // 5. Успех
                        block.style.display = 'none';
                        successEl.classList.remove('hidden');
                        setTimeout(() => successEl.classList.add('hidden'), 4000);
                    } catch (e) {
                        console.error('[push] subscribe failed:', e);
                        setButtonState('retry');
                        showError('Не удалось включить: ' + (e.message || 'неизвестная ошибка'));
                    }
                }

                btn.addEventListener('click', subscribe);

                // Проверяем, надо ли показывать блок
                (async function init() {
                    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                        return; // не поддерживается — блок не показываем
                    }
                    if (Notification.permission === 'denied') {
                        return; // заблокировано пользователем — не раздражаем
                    }

                    try {
                        const reg = await navigator.serviceWorker.ready;
                        const sub = await reg.pushManager.getSubscription();
                        if (sub && Notification.permission === 'granted') {
                            return; // уже подписан — блок не нужен
                        }
                    } catch (e) {
                        console.log('[push] init check:', e);
                        return; // SW не готов — не показываем, чтобы не путать
                    }

                    // Показываем блок с плавным появлением
                    block.style.display = 'block';
                    block.style.opacity = '0';
                    block.style.transition = 'opacity 0.3s';
                    requestAnimationFrame(() => { block.style.opacity = '1'; });
                })();
            })();
        </script>
    @endpush
</x-app-layout>
