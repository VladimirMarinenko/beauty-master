<x-app-layout>

    <style>
        .air-datepicker-cell.day-unavailable {
            background: #fee2e2 !important;
            color: #ef4444 !important;
            text-decoration: line-through;
            cursor: not-allowed;
        }
    </style>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Запись к мастеру {{ $master->name }}</h2>
            <a href="{{ route('me.index') }}" class="text-gray-500">← Назад</a>
        </div>
    </x-slot>

    <div class="app-container">
        @if(session('success'))
            <div class="mb-4 text-green-600 text-sm font-medium bg-green-50 px-4 py-3 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 text-red-600 text-sm font-medium bg-red-50 px-4 py-3 rounded-xl">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="card p-4 mb-4">
            <p class="text-xs" style="color: var(--muted);">
                Вы записываетесь к мастеру <strong style="color: var(--text);">{{ $master->name }}</strong>.
                У мастера вы записаны как <strong style="color: var(--text);">{{ $card->name }}</strong>.
            </p>
        </div>

        <form method="POST" action="{{ route('me.book.store', ['master' => $master->id]) }}" id="booking-form">
            @csrf

            <div class="card p-4 mb-4">
                <h3 class="text-base font-semibold mb-3" style="color: var(--text);">Услуга</h3>

                @if($services->isEmpty())
                    <p class="text-sm" style="color: var(--muted);">Мастер пока не добавил услуги.</p>
                @else
                    <div class="space-y-2">
                        @foreach($services as $service)
                            <label class="flex items-center gap-3 p-3 rounded-xl cursor-pointer"
                                   style="border: 1px solid var(--border); background: #fbfaff;">
                                <input type="radio" name="service_id" value="{{ $service->id }}"
                                       class="service-radio h-5 w-5"
                                       data-duration="{{ $service->duration }}"
                                       data-price="{{ $service->price }}"
                                        {{ old('service_id') == $service->id ? 'checked' : '' }}>
                                <span class="flex-1">
                                    <span class="font-medium text-sm" style="color: var(--text);">{{ $service->name }}</span>
                                    <span class="text-xs block mt-0.5" style="color: var(--muted);">
                                        {{ number_format($service->price, 0, '.', ' ') }} ₽ · {{ $service->duration }} мин
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card p-4 mb-4">
                <h3 class="text-base font-semibold mb-3" style="color: var(--text);">Дата и время</h3>

                <div class="mb-3">
                    <label for="book-date" class="block text-sm font-semibold mb-2" style="color: #475569;">
                        Дата
                    </label>
                    <input type="text" id="book-date" name="date"
                           class="input-field" placeholder="дд.мм.гггг" readonly
                           value="{{ old('date') }}">
                </div>

                <div id="slots-container" class="hidden">
                    <p class="text-sm font-semibold mb-2" style="color: #475569;">Свободное время:</p>
                    <div id="slots-list" class="flex flex-wrap gap-2"></div>
                </div>

                <p id="slots-empty" class="text-sm hidden" style="color: var(--muted);">
                    Нет свободного времени в этот день. Выберите другой.
                </p>

                <p id="slots-hint" class="text-xs mt-2" style="color: var(--muted);">
                    Выберите услугу и дату, чтобы увидеть свободное время.
                </p>

                <input type="hidden" name="start_time" id="start-time" value="{{ old('start_time') }}">
                @error('start_time')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="card p-4 mb-4">
                <label for="book-notes" class="block text-sm font-semibold mb-2" style="color: #475569;">
                    Комментарий (необязательно)
                </label>
                <textarea name="notes" id="book-notes" rows="3" class="input-field"
                          placeholder="Например: буду с ребёнком">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="btn-primary" id="book-submit" disabled style="opacity: 0.5;">
                Записаться
            </button>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const dateInput = document.getElementById('book-date');
                const serviceRadios = document.querySelectorAll('.service-radio');
                const slotsContainer = document.getElementById('slots-container');
                const slotsList = document.getElementById('slots-list');
                const slotsEmpty = document.getElementById('slots-empty');
                const slotsHint = document.getElementById('slots-hint');
                const startTimeInput = document.getElementById('start-time');
                const submitBtn = document.getElementById('book-submit');

                const slotsUrl = @json(route('me.book.slots', ['master' => $master->id]));
                const availabilityUrl = @json(route('me.book.availability', ['master' => $master->id]));

                // JSON work_hours мастера: { "1": {...}, "6": null, "7": null }
                const workHours = @json($master->effectiveWorkHours());

                let selectedDate = null;
                let selectedSlot = null;

                // { 'Y-m-d': {available, reason} } — из /availability
                let availability = {};
                // кэш загруженных месяцев: `${serviceId}:${YYYY-MM}` -> true
                const availabilityLoaded = {};
                const loadingMonths = new Set();
                let availabilityVersion = 0;
                let slotsRequestId = 0;

                // ===== Хелперы =====
                function formatDateKey(date) {
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const d = String(date.getDate()).padStart(2, '0');
                    return `${y}-${m}-${d}`;
                }

                function isoDayOfWeek(date) {
                    const jsDay = date.getDay(); // 0=вс, 1=пн, ..., 6=сб
                    return jsDay === 0 ? 7 : jsDay; // 1=пн, ..., 7=вс
                }

                function isHoliday(date) {
                    if (!workHours) return false;
                    const cfg = workHours[String(isoDayOfWeek(date))];
                    return !cfg || !cfg.start || !cfg.end;
                }

                const dp = new AirDatepicker(dateInput, {
                    locale: {
                        days: ['Воскресенье','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота'],
                        daysShort: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
                        daysMin: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
                        months: ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
                        monthsShort: ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'],
                        today: 'Сегодня', clear: 'Очистить', dateFormat: 'yyyy-MM-dd', firstDay: 1
                    },
                    dateFormat: 'yyyy-MM-dd',
                    minDate: new Date(),
                    onRenderCell({ date, cellType }) {
                        if (cellType !== 'day') return;

                        // 1. Выходной по work_hours — работает в любом месяце
                        if (isHoliday(date)) {
                            return {
                                disabled: true,
                                classes: 'day-unavailable',
                                attrs: { title: 'выходной' }
                            };
                        }

                        // 2. Прошедшая дата
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const d = new Date(date);
                        d.setHours(0, 0, 0, 0);
                        if (d < today) {
                            return {
                                disabled: true,
                                classes: 'day-unavailable',
                                attrs: { title: 'прошло' }
                            };
                        }

                        // 3. Нет свободных слотов — из availability
                        const info = availability[formatDateKey(date)];
                        if (info && info.available === false) {
                            return {
                                disabled: true,
                                classes: 'day-unavailable',
                                attrs: { title: info.reason || 'Недоступно' }
                            };
                        }
                    },
                    onSelect: function ({ date, formattedDate }) {
                        if (!date) {
                            selectedDate = null;
                            dateInput.value = '';
                            slotsHint.classList.remove('hidden');
                            slotsContainer.classList.add('hidden');
                            slotsEmpty.classList.add('hidden');
                            resetSubmit();
                            return;
                        }

                        // Выходной — отказываем
                        if (isHoliday(date)) {
                            dp.clear();
                            dateInput.value = '';
                            selectedDate = null;
                            slotsContainer.classList.add('hidden');
                            slotsHint.classList.add('hidden');
                            slotsEmpty.textContent = 'Это выходной день у мастера. Выберите другой.';
                            slotsEmpty.classList.remove('hidden');
                            resetSubmit();
                            return;
                        }

                        // Услуга не выбрана
                        if (!getSelectedService()) {
                            dp.clear();
                            dateInput.value = '';
                            selectedDate = null;
                            slotsContainer.classList.add('hidden');
                            slotsEmpty.classList.add('hidden');
                            slotsHint.textContent = 'Сначала выберите услугу.';
                            slotsHint.classList.remove('hidden');
                            resetSubmit();
                            return;
                        }

                        // День недоступен по availability
                        const info = availability[formatDateKey(date)];
                        if (info && info.available === false) {
                            dp.clear();
                            dateInput.value = '';
                            selectedDate = null;
                            slotsContainer.classList.add('hidden');
                            slotsHint.classList.add('hidden');
                            slotsEmpty.textContent = 'Этот день недоступен: ' + (info.reason || 'нет свободного времени');
                            slotsEmpty.classList.remove('hidden');
                            resetSubmit();
                            return;
                        }

                        selectedDate = formattedDate;
                        dateInput.value = selectedDate;
                        slotsHint.classList.add('hidden');
                        slotsEmpty.classList.add('hidden');
                        loadSlots();
                    },
                    // Air Datepicker 3: onShow receives a boolean; month is zero-based.
                    onShow(isFinished) {
                        if (!isFinished) {
                            loadAvailabilityAround(dp.viewDate.getMonth() + 1, dp.viewDate.getFullYear());
                        }
                    },
                    onChangeViewDate({ month, year }) { loadAvailabilityAround(month + 1, year); }
                });

                serviceRadios.forEach(r => r.addEventListener('change', () => {
                    availabilityVersion++;
                    availability = {};
                    dp.update({});
                    for (const k in availabilityLoaded) delete availabilityLoaded[k];
                    if (dp.viewDate) {
                        loadAvailabilityAround(dp.viewDate.getMonth() + 1, dp.viewDate.getFullYear());
                    }
                    loadSlots();
                }));

                // ===== Загрузка доступности за 3 месяца (текущий ± 1) =====
                function loadAvailabilityAround(month, year) {
                    loadAvailability(month, year);

                    let pm = month - 1, py = year;
                    if (pm < 1) { pm = 12; py--; }
                    loadAvailability(pm, py);

                    let nm = month + 1, ny = year;
                    if (nm > 12) { nm = 1; ny++; }
                    loadAvailability(nm, ny);
                }

                async function loadAvailability(month, year) {
                    const serviceId = getSelectedService();
                    if (!serviceId) return;

                    const m   = `${year}-${String(month).padStart(2, '0')}`;
                    const version = availabilityVersion;
                    const key = `${version}:${serviceId}:${m}`;
                    if (availabilityLoaded[key] || loadingMonths.has(key)) return;

                    loadingMonths.add(key);
                    try {
                        const res = await fetch(availabilityUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ service_id: serviceId, month: m })
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        // A response for a previously selected service must not recolor this calendar.
                        if (version !== availabilityVersion || serviceId !== getSelectedService()) return;
                        Object.assign(availability, data.days || {});
                        availabilityLoaded[key] = true;
                        dp.update({});
                    } catch (e) {
                        console.error('Availability error:', e);
                    } finally {
                        loadingMonths.delete(key);
                    }
                }

                async function loadSlots() {
                    const requestId = ++slotsRequestId;
                    const serviceId = getSelectedService();
                    if (!serviceId || !selectedDate) {
                        slotsContainer.classList.add('hidden');
                        slotsEmpty.classList.add('hidden');
                        slotsHint.classList.remove('hidden');
                        resetSubmit();
                        return;
                    }

                    slotsHint.classList.add('hidden');
                    slotsList.innerHTML = '';
                    slotsEmpty.classList.add('hidden');
                    slotsContainer.classList.add('hidden');
                    resetSubmit();

                    try {
                        const res = await fetch(slotsUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ service_id: serviceId, date: selectedDate })
                        });

                        if (!res.ok) return;
                        const data = await res.json();

                        if (requestId !== slotsRequestId) return;
                        if (!data.slots || data.slots.length === 0) {
                            slotsEmpty.textContent = 'Нет свободного времени в этот день. Выберите другой.';
                            slotsEmpty.classList.remove('hidden');
                            return;
                        }

                        data.slots.forEach(slot => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'px-4 py-2 rounded-lg text-sm font-medium';
                            btn.style.background = 'var(--bg)';
                            btn.style.color = 'var(--text)';
                            btn.style.border = '1px solid var(--border)';
                            btn.textContent = slot.time;

                            btn.addEventListener('click', function () {
                                slotsList.querySelectorAll('button').forEach(b => {
                                    b.style.background = 'var(--bg)';
                                    b.style.color = 'var(--text)';
                                });
                                btn.style.background = 'var(--primary)';
                                btn.style.color = 'white';
                                selectedSlot = slot;
                                startTimeInput.value = slot.start;
                                submitBtn.disabled = false;
                                submitBtn.style.opacity = '1';
                            });

                            slotsList.appendChild(btn);
                        });

                        slotsContainer.classList.remove('hidden');
                    } catch (e) {
                        console.error('Slots error:', e);
                    }
                }

                function getSelectedService() {
                    const checked = document.querySelector('.service-radio:checked');
                    return checked ? checked.value : null;
                }

                function resetSubmit() {
                    selectedSlot = null;
                    startTimeInput.value = '';
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                }
            });
        </script>
    @endpush
</x-app-layout>
