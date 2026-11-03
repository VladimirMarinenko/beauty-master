<section class="card p-5" aria-labelledby="usage-guide-title" id="usage-guide">
    <h3 id="usage-guide-title" class="text-base font-semibold mb-2" style="color: var(--text);">
        Инструкция по пользованию сайтом
    </h3>
    <p class="text-sm mb-4" style="color: var(--muted);">
        Нажмите на плашку, чтобы открыть или свернуть инструкцию.
        Здесь можно прочитать о двух ролях, но доступны только функции вашего аккаунта.
    </p>

    <div class="space-y-3">
        <details class="rounded-xl border" style="border-color: var(--border);">
            <summary class="cursor-pointer p-4 text-sm font-semibold rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                     style="color: var(--primary); background: var(--bg);">
                Для мастера
            </summary>
            <div class="p-4 text-sm leading-relaxed space-y-5 break-words" style="color: var(--text);">
                @include('profile.partials.master-guide')
            </div>
        </details>

        <details class="rounded-xl border" style="border-color: var(--border);">
            <summary class="cursor-pointer p-4 text-sm font-semibold rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                     style="color: var(--primary); background: var(--bg);">
                Для клиента
            </summary>
            <div class="p-4 text-sm leading-relaxed space-y-5 break-words" style="color: var(--text);">
                @include('profile.partials.client-guide')
            </div>
        </details>
    </div>
</section>
