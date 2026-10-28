<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Пригласить клиента</h2>
            <a href="{{ route('appointments.index') }}" class="text-gray-500">← Назад</a>
        </div>
    </x-slot>

    <div class="app-container">
        <div class="card p-4 mb-4 text-center">
            <p class="text-sm mb-3" style="color: var(--muted);">
                Покажите этот QR-код клиенту — он попадёт на вашу страницу и сможет записаться
            </p>

            <div class="flex justify-center my-4" style="display: flex; justify-content: center;">
                <img src="{{ $qrDataUri }}" alt="QR-код" width="280" height="280"
                     style="border-radius: 16px; background: #fff; padding: 12px;">
            </div>

            <p class="text-xs mt-2" style="color: var(--muted);">
                Ваша страница:
            </p>
            <div class="mt-2 p-2 rounded-xl" style="background: var(--bg); word-break: break-all;">
                <code style="color: var(--primary); font-size: 13px;">{{ $inviteUrl }}</code>
            </div>

            <button type="button" onclick="copyInvite()" class="btn-secondary mt-3" style="width: auto; display: inline-block;">
                Скопировать ссылку
            </button>
            <span id="copy-status" class="text-xs ml-2" style="color: #10b981; display: none;">✓ скопировано</span>
        </div>

        <div class="card p-4">
            <h3 class="text-sm font-semibold mb-2" style="color: var(--text);">Как это работает</h3>
            <ol class="text-sm space-y-2" style="color: var(--muted); padding-left: 20px; list-style: disc;">
                <li>Клиент сканирует QR или открывает ссылку</li>
                <li>Регистрируется по номеру телефона</li>
                <li>Автоматически привязывается к вам как к мастеру</li>
                <li>Может записываться на приём</li>
            </ol>
        </div>
    </div>

    @push('scripts')
        <script>
            function copyInvite() {
                const url = @json($inviteUrl);
                navigator.clipboard.writeText(url).then(() => {
                    const el = document.getElementById('copy-status');
                    el.style.display = 'inline';
                    setTimeout(() => el.style.display = 'none', 2000);
                }).catch(() => alert('Не удалось скопировать: ' + url));
            }
        </script>
    @endpush
</x-app-layout>
