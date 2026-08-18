@push('scripts')
    <script>
        const VAPID_PUBLIC_KEY = '{{ env('VAPID_PUBLIC_KEY') }}';

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
                document.getElementById('push-status').style.display = 'block';
                document.getElementById('push-status').textContent = 'Уведомления включены';
                setTimeout(() => {
                    document.getElementById('push-status').style.display = 'none';
                }, 2000);
                document.getElementById('push-subscribe-btn').style.display = 'none';
            } catch (error) {
                console.log('Не удалось подписаться на push:', error);
                document.getElementById('push-status').textContent = 'Не удалось включить уведомления';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                document.getElementById('push-subscribe-btn').style.display = 'none';
                document.getElementById('push-status').textContent = 'Уведомления не поддерживаются';
                return;
            }

            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    subscribeToPush();
                } else {
                    document.getElementById('push-status').textContent = 'Разрешение не получено';
                }
            });
        });
    </script>
@endpush
