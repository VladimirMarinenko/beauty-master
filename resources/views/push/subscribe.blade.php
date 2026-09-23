@push('scripts')
    <script>
        const VAPID_PUBLIC_KEY = '{{ config('webpush.vapid.public_key') }}';

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        async function subscribeToPush() {
            const statusEl = document.getElementById('push-status');
            const btnEl = document.getElementById('push-subscribe-btn');
            const overlayEl = document.getElementById('push-overlay');

            try {
                if (!navigator.onLine) {
                    console.log('Офлайн, подписка отложена');
                    return;
                }

                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });

                await fetch('/push-subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(subscription)
                });

                // Успех: убираем всё
                overlayEl.classList.add('push-overlay--hidden');
                btnEl.classList.remove('push-button--attention');
                btnEl.style.display = 'none';

                statusEl.style.display = 'block';
                statusEl.textContent = 'Уведомления включены';
                setTimeout(() => { statusEl.style.display = 'none'; }, 2000);
            } catch (error) {
                console.log('Не удалось подписаться на push:', error);
                statusEl.style.display = 'block';
                statusEl.textContent = 'Не удалось включить уведомления';
            }
        }

        document.addEventListener('DOMContentLoaded', async function() {
            const btnEl = document.getElementById('push-subscribe-btn');
            const statusEl = document.getElementById('push-status');
            const overlayEl = document.getElementById('push-overlay');

            if (!btnEl || !statusEl || !overlayEl) return;

            // Не поддерживается — тихо выходим
            if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                btnEl.style.display = 'none';
                overlayEl.classList.add('push-overlay--hidden');
                return;
            }

            // Разрешение запрещено — не показываем ничего
            if (Notification.permission === 'denied') {
                btnEl.style.display = 'none';
                overlayEl.classList.add('push-overlay--hidden');
                return;
            }

            // Проверяем, есть ли уже активная подписка
            let hasSubscription = false;
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                hasSubscription = !!sub;
            } catch (e) {
                console.log('Не удалось проверить подписку:', e);
            }

            // Уже подписан — ничего не показываем
            if (hasSubscription && Notification.permission === 'granted') {
                btnEl.style.display = 'none';
                overlayEl.classList.add('push-overlay--hidden');
                return;
            }

            // Показываем кнопку по центру + затемнение на 15 секунд
            overlayEl.classList.remove('push-overlay--hidden');
            btnEl.style.display = 'inline-block';
            btnEl.classList.add('push-button--attention');

            setTimeout(() => {
                overlayEl.classList.add('push-overlay--hidden');
                btnEl.classList.remove('push-button--attention');
            }, 15000);

            // Клик — запрос разрешения (обязательно для iOS)
            btnEl.addEventListener('click', async () => {
                statusEl.style.display = 'block';
                statusEl.textContent = 'Запрос разрешения...';
                try {
                    const permission = await Notification.requestPermission();
                    if (permission === 'granted') {
                        await subscribeToPush();
                    } else {
                        statusEl.textContent = 'Разрешение не получено';
                    }
                } catch (error) {
                    console.error('Ошибка запроса разрешения:', error);
                    statusEl.textContent = 'Ошибка при запросе разрешения';
                }
            });
        });
    </script>
@endpush
