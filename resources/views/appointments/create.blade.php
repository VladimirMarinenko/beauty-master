<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ __('Добавить запись') }}
            </h2>
            <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-gray-700">
                ← Назад
            </a>
        </div>
    </x-slot>

    <style>
        /* Растягиваем кликабельную зону пикера времени на всё поле */
        input[type="time"] {
            position: relative;
        }
        input[type="time"]::-webkit-calendar-picker-indicator {
            position: absolute;
            right: 0;
            top: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            opacity: 0;
            background: transparent;
        }
    </style>

    <div class="app-container">
        <div class="card p-5">
            <form action="{{ route('appointments.store') }}" method="POST" id="appointment-form">
            @csrf

            <!-- Выбор услуг (dropdown) -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Услуги</label>
                    <div class="relative">
                        <button type="button" id="services-dropdown-btn"
                                class="input-field text-left flex justify-between items-center cursor-pointer">
                            <span id="services-placeholder">Выберите услуги</span>
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="services-dropdown" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg hidden">
                            <div class="p-2 max-h-60 overflow-y-auto">
                                @foreach($services as $service)
                                    <label class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg cursor-pointer">
                                        <input type="checkbox" name="services[]" value="{{ $service->id }}"
                                               class="service-checkbox h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                                               data-price="{{ $service->price }}"
                                               data-duration="{{ $service->duration }}"
                                            {{ (is_array(old('services')) && in_array($service->id, old('services'))) ? 'checked' : '' }}>
                                        <span class="flex-1">
                                            <span class="font-medium">{{ $service->name }}</span>
                                            <span class="text-sm text-gray-600 block">{{ number_format($service->price, 2) }} ₽ · {{ $service->duration }} мин</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <p id="total-summary" class="mt-2 text-sm font-semibold text-indigo-700"></p>
                </div>

                <input type="hidden" name="service_ids" id="service_ids" value="{{ old('service_ids') }}">
                <input type="hidden" name="total_price" id="total_price" value="{{ old('total_price') }}">
                <input type="hidden" name="total_duration" id="total_duration" value="{{ old('total_duration') }}">

                <!-- Имя клиента -->
                <div class="mb-5">
                    <label for="client_name" class="block text-sm font-semibold text-gray-700 mb-2">Имя клиента</label>
                    <input type="text" name="client_name" id="client_name" value="{{ old('client_name') }}" required
                           class="input-field" placeholder="Введите имя">
                </div>

                <!-- Телефон -->
                <div class="mb-5">
                    <label for="client_phone" class="block text-sm font-semibold text-gray-700 mb-2">Телефон</label>
                    <input type="tel" name="client_phone" id="client_phone" value="{{ old('client_phone') }}"
                           class="input-field" placeholder="+7 (___) ___-__-__">
                </div>

                <!-- Email -->
                <div class="mb-5">
                    <label for="client_email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <input type="email" name="client_email" id="client_email" value="{{ old('client_email') }}"
                           class="input-field" placeholder="example@mail.com">
                </div>

                <!-- Начало -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Начало</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="block text-xs font-medium text-gray-500 mb-1">Дата</span>
                            <input type="text" id="start_date" readonly
                                   class="input-field cursor-pointer"
                                   placeholder="дд.мм.гггг">
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-500 mb-1">Время</span>
                            <input type="time" id="start_time_input" step="900"
                                   class="input-field"
                                   placeholder="чч:мм">
                        </div>
                    </div>
                    <input type="hidden" name="start_time" id="start_time" value="{{ old('start_time', $start ?? '') }}">
                    <p id="overlap-message" class="text-red-500 text-sm mt-2 hidden font-medium">Это время занято. Выберите другое.</p>
                </div>

                <!-- Плотная запись -->
                <div class="mb-5">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="dense_mode" name="dense_mode"
                               class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                            {{ old('dense_mode') ? 'checked' : '' }}>
                        <span class="ml-3 text-sm font-medium text-gray-700">
                            Плотная запись (15 мин между клиентами)
                        </span>
                    </label>
                </div>

                <!-- Своя длительность -->
                <div class="mb-5">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="custom_duration_toggle" name="custom_duration_toggle"
                               class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                            {{ old('custom_duration_toggle') ? 'checked' : '' }}>
                        <span class="ml-3 text-sm font-medium text-gray-700">
                            Своя длительность
                        </span>
                    </label>
                </div>

                <!-- Поле кастомной длительности -->
                <div id="custom-duration-container" class="mb-5 hidden">
                    <label for="custom_duration" class="block text-sm font-semibold text-gray-700 mb-2">
                        Длительность для этой записи (минут)
                    </label>
                    <input type="number" id="custom_duration" name="custom_duration" min="5" step="5"
                           value="{{ old('custom_duration', '') }}"
                           class="input-field" placeholder="Стандартная длительность">
                </div>

                <!-- Свободные слоты -->
                <div id="available-slots-container" class="mb-5 hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Свободное время:</label>
                    <div id="available-slots" class="flex flex-wrap gap-2"></div>
                </div>

                <input type="hidden" name="end_time" id="end_time" value="{{ old('end_time', $end ?? '') }}">

                <!-- Статус -->
                <div class="mb-5">
                    <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">Статус</label>
                    <select name="status" id="status" required class="input-field">
                        <option value="planned" {{ old('status') == 'planned' ? 'selected' : '' }}>Запланирована</option>
                        <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Выполнена</option>
                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Отменена</option>
                    </select>
                </div>

                <!-- Заметки -->
                <div class="mb-6">
                    <label for="notes" class="block text-sm font-semibold text-gray-700 mb-2">Заметки</label>
                    <textarea name="notes" id="notes" rows="4"
                              class="input-field" placeholder="Дополнительная информация...">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" id="submit-btn" class="btn-primary">
                    Сохранить запись
                </button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ruLocale = {
                    days: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
                    daysShort: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    daysMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                    monthsShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
                    today: 'Сегодня',
                    clear: 'Очистить',
                    dateFormat: 'dd.MM.yyyy',
                    firstDay: 1
                };

                const startDateInput = document.getElementById('start_date');
                const startTimeInput = document.getElementById('start_time_input');
                const startInput = document.getElementById('start_time');
                const endInput = document.getElementById('end_time');
                const overlapMessage = document.getElementById('overlap-message');
                const submitButton = document.getElementById('submit-btn');
                const denseModeCheckbox = document.getElementById('dense_mode');
                const customDurationToggle = document.getElementById('custom_duration_toggle');
                const customDurationContainer = document.getElementById('custom-duration-container');
                const customDurationInput = document.getElementById('custom_duration');
                const slotsContainer = document.getElementById('available-slots-container');
                const slotsDiv = document.getElementById('available-slots');
                const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
                const totalPriceInput = document.getElementById('total_price');
                const totalDurationInput = document.getElementById('total_duration');
                const serviceIdsInput = document.getElementById('service_ids');
                const totalSummary = document.getElementById('total-summary');
                const placeholder = document.getElementById('services-placeholder');
                const dropdownBtn = document.getElementById('services-dropdown-btn');
                const dropdown = document.getElementById('services-dropdown');

                // Клик в любом месте поля времени открывает нативный пикер (кроссбраузерно)
                startTimeInput.addEventListener('click', function () {
                    if (typeof this.showPicker === 'function') {
                        try {
                            this.showPicker();
                        } catch (err) {
                            // Пикер уже открыт или браузер не разрешил — игнорируем
                        }
                    }
                });

                // Календарь — только дата, неделя с понедельника
                new AirDatepicker(startDateInput, {
                    locale: ruLocale,
                    dateFormat: 'yyyy-MM-dd',
                    firstDay: 1,
                    buttons: ['today', 'clear'],
                    onSelect: function ({ date, formattedDate }) {
                        if (!date) {
                            startDateInput.value = '';
                        } else {
                            startDateInput.value = formattedDate;
                        }
                        combineDateTime();
                        recalculateEndTime();
                        checkOverlap();
                        loadAvailableSlots();
                    }
                });

                // Восстанавливаем значения при загрузке (old() или $start)
                (function initFromValue() {
                    const initial = startInput.value;
                    if (!initial || !initial.includes(' ')) return;
                    const [datePart, timePart] = initial.split(' ');
                    startDateInput.value = datePart;
                    startTimeInput.value = timePart.slice(0, 5);
                    recalculateEndTime();
                })();

                function combineDateTime() {
                    const date = startDateInput.value;
                    const time = startTimeInput.value;
                    if (date && time) {
                        startInput.value = `${date} ${time}`;
                    } else {
                        startInput.value = '';
                    }
                }

                // Автоформат при вводе: 930 → 09:30, 9 → 09
                startTimeInput.addEventListener('input', function () {
                    let v = this.value.replace(/[^\d:]/g, '');
                    if (v.length === 3 && !v.includes(':')) {
                        v = '0' + v[0] + ':' + v.slice(1);
                    } else if (v.length === 4 && !v.includes(':')) {
                        v = v.slice(0, 2) + ':' + v.slice(2);
                    }
                    this.value = v;
                });

                startTimeInput.addEventListener('change', function () {
                    const v = this.value.trim();
                    if (!v) {
                        combineDateTime();
                        recalculateEndTime();
                        checkOverlap();
                        loadAvailableSlots();
                        return;
                    }
                    const m = v.match(/^(\d{1,2}):?(\d{1,2})?$/);
                    if (m) {
                        const hh = String(Math.min(23, parseInt(m[1] || '0', 10))).padStart(2, '0');
                        const mm = String(Math.min(59, parseInt(m[2] || '0', 10))).padStart(2, '0');
                        this.value = `${hh}:${mm}`;
                    }
                    combineDateTime();
                    recalculateEndTime();
                    checkOverlap();
                    loadAvailableSlots();
                });

                // Dropdown для услуг
                dropdownBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    dropdown.classList.toggle('hidden');
                });

                document.addEventListener('click', function (e) {
                    if (!dropdown.contains(e.target) && e.target !== dropdownBtn) {
                        dropdown.classList.add('hidden');
                    }
                });

                function parseDateTime(str) {
                    if (!str || !str.includes(' ')) return null;
                    const [datePart, timePart] = str.split(' ');
                    const [year, month, day] = datePart.split('-').map(Number);
                    const [hours, minutes] = timePart.split(':').map(Number);
                    return new Date(year, month - 1, day, hours, minutes);
                }

                function formatDateTime(date) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `${year}-${month}-${day} ${hours}:${minutes}`;
                }

                function updateTotal() {
                    let totalPrice = 0;
                    let totalDuration = 0;
                    const selectedIds = [];
                    const selectedNames = [];
                    serviceCheckboxes.forEach(cb => {
                        if (cb.checked) {
                            totalPrice += parseFloat(cb.dataset.price);
                            totalDuration += parseInt(cb.dataset.duration, 10);
                            selectedIds.push(cb.value);
                            const label = cb.closest('label');
                            if (label) {
                                const name = label.querySelector('.font-medium')?.textContent.trim();
                                if (name) selectedNames.push(name);
                            }
                        }
                    });
                    totalPriceInput.value = totalPrice.toFixed(2);
                    totalDurationInput.value = totalDuration;
                    serviceIdsInput.value = JSON.stringify(selectedIds);
                    totalSummary.textContent = `Итого: ${totalPrice.toFixed(2)} ₽ · ${totalDuration} мин`;
                    placeholder.textContent = selectedIds.length > 0 ? selectedNames.join(', ') : 'Выберите услуги';
                    recalculateEndTime();
                }

                serviceCheckboxes.forEach(cb => cb.addEventListener('change', updateTotal));

                function getEffectiveDuration() {
                    if (customDurationToggle.checked && customDurationInput.value) {
                        return parseInt(customDurationInput.value, 10);
                    }
                    return parseInt(totalDurationInput.value || 0, 10);
                }

                function recalculateEndTime() {
                    const duration = getEffectiveDuration();
                    const startValue = startInput.value;
                    if (duration && startValue) {
                        const startDate = parseDateTime(startValue);
                        if (startDate) {
                            const endDate = new Date(startDate.getTime() + duration * 60000);
                            endInput.value = formatDateTime(endDate);
                        }
                    } else {
                        endInput.value = '';
                    }
                }

                async function checkOverlap() {
                    if (!navigator.onLine) {
                        overlapMessage.classList.add('hidden');
                        submitButton.disabled = false;
                        return;
                    }
                    const startValue = startInput.value;
                    if (!startValue) {
                        overlapMessage.classList.add('hidden');
                        submitButton.disabled = false;
                        return;
                    }
                    const duration = getEffectiveDuration();
                    if (!duration) return;
                    const startDate = parseDateTime(startValue);
                    if (!startDate) return;
                    const endDate = new Date(startDate.getTime() + duration * 60000);
                    const endValue = formatDateTime(endDate);

                    try {
                        const response = await fetch('{{ route('appointments.check-overlap') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                start_time: startValue,
                                end_time: endValue
                            })
                        });
                        if (!response.ok) return;
                        const data = await response.json();
                        if (data.available) {
                            overlapMessage.classList.add('hidden');
                            startInput.classList.remove('border-red-500');
                            submitButton.disabled = false;
                        } else {
                            overlapMessage.classList.remove('hidden');
                            submitButton.disabled = true;
                        }
                    } catch (error) {
                        console.log('Офлайн или ошибка сети, проверка пропущена');
                        overlapMessage.classList.add('hidden');
                        submitButton.disabled = false;
                    }
                }

                async function loadAvailableSlots() {
                    if (!navigator.onLine) {
                        slotsContainer.classList.add('hidden');
                        return;
                    }
                    if (!denseModeCheckbox.checked) {
                        slotsContainer.classList.add('hidden');
                        return;
                    }
                    const firstChecked = Array.from(serviceCheckboxes).find(cb => cb.checked);
                    if (!firstChecked) return;
                    const serviceId = firstChecked.value;
                    const dateValue = startDateInput.value;
                    if (!serviceId || !dateValue) return;
                    let url = `{{ route('appointments.available-slots') }}?service_id=${serviceId}&date=${dateValue}`;
                    const duration = getEffectiveDuration();
                    if (duration) {
                        url += `&duration=${duration}`;
                    }

                    try {
                        const response = await fetch(url);
                        if (!response.ok) return;
                        const data = await response.json();
                        if (data.slots && data.slots.length > 0) {
                            slotsDiv.innerHTML = '';
                            let activeSlotButton = null;
                            data.slots.forEach(slot => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium text-gray-800 hover:bg-indigo-100 active:bg-indigo-200 transition';
                                btn.textContent = slot.start.slice(11, 16);
                                btn.dataset.start = slot.start;
                                btn.dataset.end = slot.end;
                                btn.addEventListener('click', function () {
                                    if (activeSlotButton) {
                                        activeSlotButton.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
                                        activeSlotButton.classList.add('bg-gray-100', 'text-gray-800');
                                    }
                                    btn.classList.remove('bg-gray-100', 'text-gray-800');
                                    btn.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
                                    activeSlotButton = btn;

                                    const [datePart, timePart] = slot.start.split(' ');
                                    startDateInput.value = datePart;
                                    startTimeInput.value = timePart.slice(0, 5);
                                    combineDateTime();
                                    endInput.value = slot.end;
                                    recalculateEndTime();
                                    checkOverlap();
                                });
                                slotsDiv.appendChild(btn);
                            });
                            slotsContainer.classList.remove('hidden');
                        } else {
                            slotsContainer.classList.add('hidden');
                        }
                    } catch (error) {
                        console.log('Офлайн или ошибка сети, слоты не загружены');
                        slotsContainer.classList.add('hidden');
                    }
                }

                customDurationToggle.addEventListener('change', () => {
                    if (customDurationToggle.checked) {
                        customDurationContainer.classList.remove('hidden');
                        customDurationInput.value = customDurationInput.value || getEffectiveDuration();
                    } else {
                        customDurationContainer.classList.add('hidden');
                        customDurationInput.value = '';
                    }
                    recalculateEndTime();
                    checkOverlap();
                    loadAvailableSlots();
                });

                customDurationInput.addEventListener('change', () => {
                    recalculateEndTime();
                    checkOverlap();
                    loadAvailableSlots();
                });

                // Отправка формы
                document.getElementById('appointment-form').addEventListener('submit', async function (e) {
                    e.preventDefault();
                    combineDateTime();
                    const selectedIds = Array.from(serviceCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
                    if (selectedIds.length === 0) {
                        alert('Выберите хотя бы одну услугу');
                        return;
                    }
                    if (!startInput.value) {
                        alert('Укажите дату и время начала');
                        return;
                    }

                    const data = {
                        _token: '{{ csrf_token() }}',
                        service_ids: JSON.stringify(selectedIds),
                        total_price: totalPriceInput.value,
                        total_duration: totalDurationInput.value,
                        client_name: document.getElementById('client_name').value,
                        client_phone: document.getElementById('client_phone').value,
                        client_email: document.getElementById('client_email').value,
                        start_time: startInput.value,
                        end_time: endInput.value,
                        status: document.getElementById('status').value,
                        notes: document.getElementById('notes').value,
                        custom_duration: customDurationInput.value || '',
                    };

                    try {
                        const response = await fetch(this.action, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(data)
                        });

                        if (response.ok) {
                            window.location.href = '{{ route('appointments.index') }}';
                        } else {
                            const err = await response.json();
                            if (err.errors) {
                                alert(Object.values(err.errors).flat().join('\n'));
                            } else {
                                await saveOffline(data);
                            }
                        }
                    } catch (error) {
                        await saveOffline(data);
                    }
                });

                async function saveOffline(data) {
                    try {
                        const db = await openDatabase();
                        const tx = db.transaction('pendingAppointments', 'readwrite');
                        tx.objectStore('pendingAppointments').add(data);
                        await tx.complete;

                        try {
                            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                                const registration = await navigator.serviceWorker.ready;
                                await registration.sync.register('sync-appointments');
                            }
                        } catch (syncError) {
                            console.warn('Background Sync недоступен. Запись сохранена локально.');
                        }

                        alert('Нет интернета. Запись сохранена локально. Она будет отправлена автоматически при появлении интернета.');
                        window.location.href = '{{ route('appointments.index') }}';
                    } catch (error) {
                        console.error('Ошибка сохранения офлайн:', error);
                        alert('Не удалось сохранить запись офлайн.');
                    }
                }

                function openDatabase() {
                    return new Promise((resolve, reject) => {
                        const request = indexedDB.open('BeautyManagerDB', 1);
                        request.onupgradeneeded = function (event) {
                            const db = event.target.result;
                            if (!db.objectStoreNames.contains('pendingAppointments')) {
                                db.createObjectStore('pendingAppointments', { keyPath: 'id', autoIncrement: true });
                            }
                        };
                        request.onsuccess = function (event) { resolve(event.target.result); };
                        request.onerror = function (event) { reject(event.target.error); };
                    });
                }

                // Инициализация
                updateTotal();
                if (denseModeCheckbox.checked) loadAvailableSlots();
            });
        </script>
    @endpush
</x-app-layout>
