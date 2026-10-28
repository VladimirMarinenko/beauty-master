<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ __('Профиль') }}
            </h2>
            <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-gray-700">
                ← Назад
            </a>
        </div>
    </x-slot>

    <div class="app-container">
        @php
            // Определяем до @if — используется и в HTML, и в @push('scripts')
            $weekLabels = \App\Models\User::weekDayLabels();
            $workHours = $user->isMaster() ? $user->effectiveWorkHours() : [];
            $copyUrl = $user->isMaster() ? ($user->publicPageUrl() ?? '') : '';
        @endphp

        @if (session('status') === 'profile-updated')
            <div class="mb-4 text-green-600 text-sm font-medium bg-green-50 px-4 py-3 rounded-xl">
                Сохранено
            </div>
        @endif

        <div class="space-y-4">
            <!-- Личные данные -->
            <div class="card p-5">
                <h3 class="text-base font-semibold mb-4" style="color: var(--text);">Личные данные</h3>
                <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('patch')

                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Имя</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                               class="input-field" autocomplete="name">
                        @if($errors->get('name'))
                            <p class="text-red-500 text-sm mt-1">{{ $errors->first('name') }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            Email
                            @if($user->isMaster())
                                <span class="text-red-500">*</span>
                            @endif
                        </label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                               class="input-field" autocomplete="username"
                               placeholder="{{ $user->isClient() ? 'Необязательно' : '' }}"
                               @if($user->isMaster()) required @endif>
                        @if($user->isClient())
                            <p class="text-xs mt-1" style="color: var(--muted);">
                                Если укажете email — сможете входить по нему и восстанавливать пароль.
                            </p>
                        @endif
                        @if($errors->get('email'))
                            <p class="text-red-500 text-sm mt-1">{{ $errors->first('email') }}</p>
                        @endif
                    </div>

                    <button type="submit" class="btn-primary">Сохранить</button>
                </form>
            </div>

            @if($user->isMaster())
                {{-- Для записи клиентов --}}
                <div class="card p-5">
                    <h3 class="text-base font-semibold mb-1" style="color: var(--text);">
                        Для записи клиентов
                    </h3>
                    <p class="text-sm mb-3" style="color: var(--muted);">
                        Клиенты переходят по этой ссылке или сканируют QR — и записываются к вам.
                    </p>

                    @if($copyUrl)
                        <div class="p-3 rounded-xl mb-3" style="background: var(--bg); word-break: break-all;">
                            <code style="color: var(--primary); font-size: 13px;">{{ $copyUrl }}</code>
                        </div>

                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" id="copy-profile-invite" class="btn-secondary" style="width: auto;">
                                Скопировать ссылку
                            </button>
                            <a href="{{ route('master.invite') }}" class="btn-primary" style="width: auto;">
                                QR и поделиться
                            </a>
                        </div>
                        <span id="profile-copy-status" class="text-xs mt-2" style="color: #10b981; display: none;">
                            ✓ скопировано
                        </span>
                    @else
                        <div class="p-3 rounded-xl mb-3" style="background: #fef3c7; color: #92400e;">
                            <p class="text-xs">Ссылка ещё не создана.</p>
                        </div>
                        <a href="{{ route('master.invite') }}" class="btn-primary" style="width: auto;">
                            Создать ссылку
                        </a>
                    @endif
                </div>

                {{-- Рабочие часы --}}
                <div class="card p-5">
                    <h3 class="text-base font-semibold mb-1" style="color: var(--text);">
                        🕒 Рабочие часы
                    </h3>
                    <p class="text-sm mb-4" style="color: var(--muted);">
                        Клиенты увидят для записи только эти часы. В выходные дни записаться нельзя.
                    </p>

                    @if($errors->has('work_hours'))
                        <div class="mb-3 p-3 rounded-xl" style="background: #fef2f2; color: #dc2626; font-size: 14px;">
                            {{ $errors->first('work_hours') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.update') }}" id="work-hours-form">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="name" value="{{ $user->name }}">
                        <input type="hidden" name="email" value="{{ $user->email }}">

                        <div class="mb-4">
                            <label for="slot_step" class="block text-sm font-semibold mb-2" style="color: var(--text);">
                                Шаг записи (интервал между слотами)
                            </label>
                            <select name="slot_step" id="slot_step" class="input-field" style="max-width: 260px;">
                                @foreach(\App\Models\User::allowedSlotSteps() as $step)
                                    <option value="{{ $step }}"
                                            @selected((int) old('slot_step', $user->slot_step ?? 5) === $step)>
                                    {{ $step }} минут
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs mt-1" style="color: var(--muted);">
                                Клиенты увидят слоты с этим интервалом.
                            </p>
                            @error('slot_step')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            @foreach($weekLabels as $iso => $label)
                                @php
                                    $day = $workHours[(string) $iso] ?? null;
                                    $isWorking = !empty($day);

                                    $start = old("work_hours.$iso.start", $day['start'] ?? '10:00');
                                    $end = old("work_hours.$iso.end", $day['end'] ?? '19:00');
                                    $breakStart = old("work_hours.$iso.break_start", $day['break_start'] ?? '');
                                    $breakEnd = old("work_hours.$iso.break_end", $day['break_end'] ?? '');
                                    $enabled = old("work_hours.$iso.enabled", $isWorking);

                                    $dayErrors = [];
                                    foreach (['start', 'end', 'break_start', 'break_end'] as $field) {
                                        if ($errors->has("work_hours.$iso.$field")) {
                                            $dayErrors[$field] = $errors->first("work_hours.$iso.$field");
                                        }
                                    }
                                @endphp
                                <div class="rounded-xl p-3"
                                     style="background: var(--bg); border: 1px solid {{ !empty($dayErrors) ? '#dc2626' : 'var(--border)' }};">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <label class="flex items-center gap-2 cursor-pointer" style="min-width: 150px;">
                                            <input type="checkbox"
                                                   name="work_hours[{{ $iso }}][enabled]"
                                                   value="1"
                                                   class="work-day-toggle h-5 w-5"
                                                   data-day="{{ $iso }}"
                                                   @checked($enabled)>
                                            <span class="text-sm font-semibold" style="color: var(--text);">{{ $label }}</span>
                                        </label>

                                        <div class="work-day-fields flex items-center gap-2 flex-wrap" data-day="{{ $iso }}"
                                             style="{{ $enabled ? '' : 'opacity: 0.4; pointer-events: none;' }}">
                                            <label class="text-xs" style="color: var(--muted);">с</label>
                                            <input type="time" name="work_hours[{{ $iso }}][start]"
                                                   value="{{ $start }}" class="input-field work-time-input"
                                                   data-day="{{ $iso }}" data-field="start"
                                                   style="padding: 6px 10px; width: auto;">

                                            <label class="text-xs" style="color: var(--muted);">по</label>
                                            <input type="time" name="work_hours[{{ $iso }}][end]"
                                                   value="{{ $end }}" class="input-field work-time-input"
                                                   data-day="{{ $iso }}" data-field="end"
                                                   style="padding: 6px 10px; width: auto;">

                                            <label class="text-xs ml-2" style="color: var(--muted);">обед с</label>
                                            <input type="time" name="work_hours[{{ $iso }}][break_start]"
                                                   value="{{ $breakStart }}" class="input-field work-time-input"
                                                   data-day="{{ $iso }}" data-field="break_start"
                                                   style="padding: 6px 10px; width: auto;">

                                            <label class="text-xs" style="color: var(--muted);">по</label>
                                            <input type="time" name="work_hours[{{ $iso }}][break_end]"
                                                   value="{{ $breakEnd }}" class="input-field work-time-input"
                                                   data-day="{{ $iso }}" data-field="break_end"
                                                   style="padding: 6px 10px; width: auto;">
                                        </div>
                                    </div>

                                    <ul class="mt-2 text-xs day-errors" data-day="{{ $iso }}"
                                        style="color: #dc2626; padding-left: 18px; list-style: disc; {{ empty($dayErrors) ? 'display:none;' : '' }}">
                                        @foreach($dayErrors as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>

                        <button type="submit" class="btn-primary mt-4">Сохранить рабочие часы</button>
                    </form>
                </div>
        @endif

        <!-- Смена пароля -->
            <div class="card p-5">
                <h3 class="text-base font-semibold mb-4" style="color: var(--text);">Сменить пароль</h3>
                <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                    @csrf
                    @method('put')

                    <div>
                        <label for="update_password_current_password" class="block text-sm font-semibold text-gray-700 mb-2">Текущий пароль</label>
                        <input id="update_password_current_password" name="current_password" type="password"
                               class="input-field" autocomplete="current-password">
                        @if($errors->updatePassword->get('current_password'))
                            <p class="text-red-500 text-sm mt-1">{{ $errors->updatePassword->first('current_password') }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="update_password_password" class="block text-sm font-semibold text-gray-700 mb-2">Новый пароль</label>
                        <input id="update_password_password" name="password" type="password"
                               class="input-field" autocomplete="new-password">
                        @if($errors->updatePassword->get('password'))
                            <p class="text-red-500 text-sm mt-1">{{ $errors->updatePassword->first('password') }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="update_password_password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">Подтверждение пароля</label>
                        <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                               class="input-field" autocomplete="new-password">
                        @if($errors->updatePassword->get('password_confirmation'))
                            <p class="text-red-500 text-sm mt-1">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                        @endif
                    </div>

                    <button type="submit" class="btn-primary">Обновить пароль</button>
                </form>
            </div>

            <!-- Выход -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-secondary text-red-600" style="background: #fef2f2; color: #dc2626;">
                    Выйти из аккаунта
                </button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // === Копирование ссылки приглашения ===
                const copyBtn = document.getElementById('copy-profile-invite');
                if (copyBtn) {
                    const url = @json($copyUrl ?? '');
                    copyBtn.addEventListener('click', function () {
                        if (!url) return;
                        navigator.clipboard.writeText(url).then(() => {
                            const s = document.getElementById('profile-copy-status');
                            if (s) {
                                s.style.display = 'inline';
                                setTimeout(() => s.style.display = 'none', 2000);
                            }
                        }).catch(() => alert('Не удалось скопировать: ' + url));
                    });
                }

                // === Переключение рабочих дней ===
                document.querySelectorAll('.work-day-toggle').forEach(function (checkbox) {
                    const day = checkbox.dataset.day;
                    const fields = document.querySelector('.work-day-fields[data-day="' + day + '"]');
                    if (!fields) return;

                    function sync() {
                        if (checkbox.checked) {
                            fields.style.opacity = '';
                            fields.style.pointerEvents = '';
                        } else {
                            fields.style.opacity = '0.4';
                            fields.style.pointerEvents = 'none';
                        }
                    }
                    checkbox.addEventListener('change', sync);
                    sync();
                });

                // === Клиентская валидация рабочих часов ===
                const workForm = document.getElementById('work-hours-form');
                if (workForm) {
                    const dayLabels = @json($weekLabels ?? []);

                    function clearDayErrors(day) {
                        const ul = document.querySelector('.day-errors[data-day="' + day + '"]');
                        if (ul) { ul.innerHTML = ''; ul.style.display = 'none'; }
                        const fields = document.querySelector('.work-day-fields[data-day="' + day + '"]');
                        const wrap = fields ? fields.closest('.rounded-xl') : null;
                        if (wrap) wrap.style.borderColor = 'var(--border)';
                    }

                    function addDayError(day, msg) {
                        const ul = document.querySelector('.day-errors[data-day="' + day + '"]');
                        const fields = document.querySelector('.work-day-fields[data-day="' + day + '"]');
                        const wrap = fields ? fields.closest('.rounded-xl') : null;
                        if (ul) {
                            ul.style.display = 'block';
                            const li = document.createElement('li');
                            li.textContent = msg;
                            ul.appendChild(li);
                        }
                        if (wrap) wrap.style.borderColor = '#dc2626';
                    }

                    function validateWorkHours() {
                        let ok = true;

                        for (let d = 1; d <= 7; d++) {
                            clearDayErrors(d);

                            const enabled = workForm.querySelector('input[name="work_hours[' + d + '][enabled]"]')?.checked;
                            if (!enabled) continue;

                            const label = dayLabels[d] || ('День ' + d);
                            const start = workForm.querySelector('input[name="work_hours[' + d + '][start]"]')?.value;
                            const end = workForm.querySelector('input[name="work_hours[' + d + '][end]"]')?.value;
                            const breakStart = workForm.querySelector('input[name="work_hours[' + d + '][break_start]"]')?.value;
                            const breakEnd = workForm.querySelector('input[name="work_hours[' + d + '][break_end]"]')?.value;

                            if (start && end && end <= start) {
                                addDayError(d, label + ': конец смены должен быть позже начала (' + start + ')');
                                ok = false;
                                continue;
                            }

                            if ((breakStart && !breakEnd) || (!breakStart && breakEnd)) {
                                addDayError(d, label + ': укажите обе границы обеда');
                                ok = false;
                                continue;
                            }

                            if (breakStart && breakEnd && breakEnd <= breakStart) {
                                addDayError(d, label + ': конец обеда должен быть позже начала (' + breakStart + ')');
                                ok = false;
                                continue;
                            }

                            if (breakStart && breakEnd) {
                                if (start && breakStart < start) {
                                    addDayError(d, label + ': обед должен быть внутри смены (не раньше ' + start + ')');
                                    ok = false;
                                }
                                if (end && breakEnd > end) {
                                    addDayError(d, label + ': обед должен быть внутри смены (не позже ' + end + ')');
                                    ok = false;
                                }
                            }
                        }

                        return ok;
                    }

                    workForm.querySelectorAll('.work-time-input, .work-day-toggle').forEach(function (el) {
                        el.addEventListener('change', validateWorkHours);
                    });

                    workForm.addEventListener('submit', function (e) {
                        if (!validateWorkHours()) {
                            e.preventDefault();
                            const first = workForm.querySelector('.day-errors li');
                            if (first) first.closest('.rounded-xl').scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>
