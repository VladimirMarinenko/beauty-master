<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">{{ __('Добавить запись') }}</h2>
            <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-gray-700">← Назад</a>
        </div>
    </x-slot>

    <style>
        input[type="time"] { position: relative; }
        input[type="time"]::-webkit-calendar-picker-indicator {
            position: absolute; right: 0; top: 0; width: 100%; height: 100%;
            cursor: pointer; opacity: 0; background: transparent;
        }
    </style>

    <div class="app-container">
        <div class="card p-5" style="overflow: visible;">
            <form action="{{ route('appointments.store') }}" method="POST" id="appointment-form">
            @csrf

            <!-- ============================================ -->
                <!-- КЛИЕНТ -->
                <!-- ============================================ -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Клиент <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <button type="button" id="clients-dropdown-btn"
                                class="input-field text-left flex justify-between items-center cursor-pointer">
                            <span id="clients-placeholder">Выберите клиента</span>
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="clients-dropdown" class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg hidden">
                            <div class="p-2 border-b border-gray-100">
                                <input type="text" id="client-search-input"
                                       class="input-field"
                                       style="padding: 10px 14px; font-size: 14px;"
                                       placeholder="Поиск: имя, телефон или соц. сеть"
                                       autocomplete="off">
                            </div>
                            <div class="p-2 max-h-72 overflow-y-auto" id="clients-list">
                                @foreach($clients as $client)
                                    <button type="button"
                                            class="client-option w-full text-left flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg cursor-pointer"
                                            data-client-id="{{ $client->id }}"
                                            data-client-name="{{ $client->name }}"
                                            data-client-phone="{{ $client->phone }}"
                                            data-client-contact="{{ $client->contact }}"
                                            data-client-permanent="{{ $client->is_permanent ? '1' : '0' }}"
                                            data-client-services="{{ $client->services->pluck('id')->toJson() }}">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium">{{ $client->name }}</span>
                                                @if($client->is_permanent)
                                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full" style="background: #eef2ff; color: var(--primary);">
                                постоянный
                            </span>
                                                @endif
                                            </div>
                                            @if($client->phone)
                                                <span class="text-xs text-gray-600 block">📞 {{ $client->phone }}</span>
                                            @endif
                                        </div>
                                    </button>
                                @endforeach
                                @if($clients->isEmpty())
                                    <div class="p-4 text-center text-sm text-gray-500">
                                        У вас пока нет клиентов.
                                        <a href="{{ route('clients.create') }}" class="block mt-2" style="color: var(--primary);">
                                            + Добавить клиента
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id') }}">
                    <p id="client-status" class="text-xs text-gray-500 mt-1"></p>
                </div>

                <!-- ============================================ -->
                <!-- УСЛУГИ -->
                <!-- ============================================ -->
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
                        <div id="services-dropdown" class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg hidden">
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

                <!-- ============================================ -->
                <!-- ИМЯ (hidden) / ТЕЛЕФОН / EMAIL -->
                <!-- ============================================ -->
                <input type="hidden" name="client_name" id="client_name" value="{{ old('client_name') }}">

                <div class="mb-5">
                    <label for="client_phone" class="block text-sm font-semibold text-gray-700 mb-2">Телефон</label>
                    <input type="tel" name="client_phone" id="client_phone" value="{{ old('client_phone') }}" class="input-field" placeholder="+7 (___) ___-__-__">
                </div>

                <div class="mb-5">
                    <label for="client_email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <input type="email" name="client_email" id="client_email" value="{{ old('client_email') }}" class="input-field" placeholder="example@mail.com">
                </div>

                <!-- ============================================ -->
                <!-- НАЧАЛО -->
                <!-- ============================================ -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Начало</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="block text-xs font-medium text-gray-500 mb-1">Дата</span>
                            <input type="text" id="start_date" readonly class="input-field cursor-pointer" placeholder="дд.мм.гггг">
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-500 mb-1">Время</span>
                            <input type="time" id="start_time_input" step="300" class="input-field" placeholder="чч:мм">
                        </div>
                    </div>
                    <input type="hidden" name="start_time" id="start_time" value="{{ old('start_time', $start ?? '') }}">
                    <p id="overlap-message" class="text-red-500 text-sm mt-2 hidden font-medium">Это время занято. Выберите другое.</p>
                </div>

                <div class="mb-5">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="dense_mode" name="dense_mode"
                               class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                            {{ old('dense_mode') ? 'checked' : '' }}>
                        <span class="ml-3 text-sm font-medium text-gray-700">Плотная запись (15 мин между клиентами)</span>
                    </label>
                </div>

                <div class="mb-5">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="custom_duration_toggle" name="custom_duration_toggle"
                               class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                            {{ old('custom_duration_toggle') ? 'checked' : '' }}>
                        <span class="ml-3 text-sm font-medium text-gray-700">Своя длительность</span>
                    </label>
                </div>

                <div id="custom-duration-container" class="mb-5 hidden">
                    <label for="custom_duration" class="block text-sm font-semibold text-gray-700 mb-2">Длительность для этой записи (минут)</label>
                    <input type="number" id="custom_duration" name="custom_duration" min="1" step="1"
                           value="{{ old('custom_duration', '') }}" class="input-field" placeholder="Например: 102">

                    <div class="mt-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="is_fixed_price" name="is_fixed_price" value="1"
                                   class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                                {{ old('is_fixed_price', true) ? 'checked' : '' }}>
                            <span class="ml-3 text-sm font-medium text-gray-700">Фиксированная стоимость</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1 ml-8">
                            Если снять — стоимость = цена услуги × количество минут.
                        </p>
                    </div>
                </div>

                <div id="available-slots-container" class="mb-5 hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Свободное время:</label>
                    <div id="available-slots" class="flex flex-wrap gap-2"></div>
                </div>

                <input type="hidden" name="end_time" id="end_time" value="{{ old('end_time', $end ?? '') }}">

                <div class="mb-5">
                    <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">Статус</label>
                    <select name="status" id="status" required class="input-field">
                        <option value="planned" {{ old('status') == 'planned' ? 'selected' : '' }}>Запланирована</option>
                        <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Выполнена</option>
                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Отменена</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label for="notes" class="block text-sm font-semibold text-gray-700 mb-2">Заметки</label>
                    <textarea name="notes" id="notes" rows="4" class="input-field">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" id="submit-btn" class="btn-primary">Сохранить запись</button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // =========================
                // Элементы
                // =========================
                const ruLocale = {
                    days: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
                    daysShort: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    daysMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                    monthsShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
                    today: 'Сегодня', clear: 'Очистить', dateFormat: 'dd.MM.yyyy', firstDay: 1
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
                const isFixedPriceCheckbox = document.getElementById('is_fixed_price');
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

                // Клиенты
                const clientsDropdownBtn = document.getElementById('clients-dropdown-btn');
                const clientsDropdown = document.getElementById('clients-dropdown');
                const clientsPlaceholder = document.getElementById('clients-placeholder');
                const clientIdInput = document.getElementById('client_id');
                const clientNameInput = document.getElementById('client_name');
                const clientPhoneInput = document.getElementById('client_phone');
                const clientEmailInput = document.getElementById('client_email');
                const clientStatus = document.getElementById('client-status');

                // =========================
                // Работа со временем
                // =========================
                startTimeInput.addEventListener('click', function () {
                    if (typeof this.showPicker === 'function') {
                        try { this.showPicker(); } catch (err) {}
                    }
                });

                new AirDatepicker(startDateInput, {
                    locale: ruLocale, dateFormat: 'yyyy-MM-dd', firstDay: 1, buttons: ['today', 'clear'],
                    onSelect: function ({ date, formattedDate }) {
                        startDateInput.value = date ? formattedDate : '';
                        combineDateTime(); recalculateEndTime(); checkOverlap(); loadAvailableSlots();
                    }
                });

                (function initFromValue() {
                    const initial = startInput.value;
                    if (!initial || !initial.includes(' ')) return;
                    const [datePart, timePart] = initial.split(' ');
                    startDateInput.value = datePart;
                    startTimeInput.value = timePart.slice(0, 5);
                    recalculateEndTime();
                })();

                function combineDateTime() {
                    const date = startDateInput.value, time = startTimeInput.value;
                    startInput.value = (date && time) ? `${date} ${time}` : '';
                }

                startTimeInput.addEventListener('input', function () {
                    let v = this.value.replace(/[^\d:]/g, '');
                    if (v.length === 3 && !v.includes(':')) v = '0' + v[0] + ':' + v.slice(1);
                    else if (v.length === 4 && !v.includes(':')) v = v.slice(0, 2) + ':' + v.slice(2);
                    this.value = v;
                });

                startTimeInput.addEventListener('change', function () {
                    const v = this.value.trim();
                    if (!v) { combineDateTime(); recalculateEndTime(); checkOverlap(); loadAvailableSlots(); return; }
                    const m = v.match(/^(\d{1,2}):?(\d{1,2})?$/);
                    if (m) {
                        const hh = String(Math.min(23, parseInt(m[1] || '0', 10))).padStart(2, '0');
                        const mm = String(Math.min(59, parseInt(m[2] || '0', 10))).padStart(2, '0');
                        this.value = `${hh}:${mm}`;
                    }
                    combineDateTime(); recalculateEndTime(); checkOverlap(); loadAvailableSlots();
                });

                // =========================
                // Работа с дропдаунами
                // =========================
                dropdownBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    clientsDropdown.classList.add('hidden');
                    dropdown.classList.toggle('hidden');
                });

                clientsDropdownBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    dropdown.classList.add('hidden');
                    clientsDropdown.classList.toggle('hidden');

                    if (!clientsDropdown.classList.contains('hidden') && clientSearchInput) {
                        clientSearchInput.value = '';
                        clientsListEl.innerHTML = initialClientsHTML;
                    }
                });

                document.addEventListener('click', function (e) {
                    if (!dropdown.contains(e.target) && e.target !== dropdownBtn) dropdown.classList.add('hidden');
                    if (!clientsDropdown.contains(e.target) && e.target !== clientsDropdownBtn) clientsDropdown.classList.add('hidden');
                });

                // =========================
                // Обработка выбора клиента
                // =========================
                const clientsListEl = document.getElementById('clients-list');
                const clientSearchInput = document.getElementById('client-search-input');
                const initialClientsHTML = clientsListEl ? clientsListEl.innerHTML : '';
                let clientSearchTimer = null;

                function escapeHtml(s) {
                    return String(s ?? '').replace(/[&<>"']/g, m =>
                        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])
                    );
                }

                function applyClientChoice(btn) {
                    const id = btn.dataset.clientId;
                    const name = btn.dataset.clientName;
                    const phone = btn.dataset.clientPhone || '';
                    const contact = btn.dataset.clientContact || '';
                    const isPermanent = btn.dataset.clientPermanent === '1';
                    let serviceIds = [];
                    try { serviceIds = JSON.parse(btn.dataset.clientServices || '[]'); } catch (e) { serviceIds = []; }

                    clientIdInput.value = id;
                    clientNameInput.value = name;
                    if (phone) clientPhoneInput.value = phone;
                    if (contact && contact.includes('@')) clientEmailInput.value = contact;

                    clientsPlaceholder.textContent = name;

                    if (isPermanent && serviceIds.length > 0) {
                        clientStatus.textContent = 'Постоянный клиент — услуги проставлены автоматически.';
                    } else if (isPermanent) {
                        clientStatus.textContent = 'Постоянный клиент (нет постоянных услуг).';
                    } else {
                        clientStatus.textContent = 'Разовая запись.';
                    }

                    serviceCheckboxes.forEach(cb => cb.checked = false);
                    if (isPermanent && serviceIds.length > 0) {
                        serviceIds.forEach(sid => {
                            const cb = Array.from(serviceCheckboxes).find(c => c.value == sid);
                            if (cb) cb.checked = true;
                        });
                    }

                    updateTotal();
                    clientsDropdown.classList.add('hidden');
                    if (clientSearchInput) clientSearchInput.value = '';
                }

                clientsListEl?.addEventListener('click', function (e) {
                    const btn = e.target.closest('.client-option');
                    if (!btn) return;
                    applyClientChoice(btn);
                });

                function renderClients(clients) {
                    if (!clients.length) {
                        clientsListEl.innerHTML = '<div class="p-4 text-center text-sm text-gray-500">Ничего не найдено</div>';
                        return;
                    }
                    clientsListEl.innerHTML = clients.map(c => `
        <button type="button"
                class="client-option w-full text-left flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg cursor-pointer"
                data-client-id="${c.id}"
                data-client-name="${escapeHtml(c.name)}"
                data-client-phone="${escapeHtml(c.phone || '')}"
                data-client-contact="${escapeHtml(c.contact || '')}"
                data-client-permanent="${c.is_permanent ? '1' : '0'}"
                data-client-services='${JSON.stringify(c.service_ids || [])}'>
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <span class="font-medium">${escapeHtml(c.name)}</span>
                    ${c.is_permanent ? '<span class="text-xs font-medium px-2 py-0.5 rounded-full" style="background: #eef2ff; color: var(--primary);">постоянный</span>' : ''}
                </div>
                ${c.phone ? `<span class="text-xs text-gray-600 block">📞 ${escapeHtml(c.phone)}</span>` : ''}
            </div>
        </button>
    `).join('');
                }

                clientSearchInput?.addEventListener('input', function () {
                    const q = this.value.trim();
                    clearTimeout(clientSearchTimer);

                    if (!q) {
                        clientsListEl.innerHTML = initialClientsHTML;
                        return;
                    }

                    clientSearchTimer = setTimeout(async () => {
                        try {
                            const res = await fetch('{{ route('clients.search') }}?q=' + encodeURIComponent(q), {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin'
                            });
                            if (!res.ok) return;
                            const clients = await res.json();
                            renderClients(clients);
                        } catch (err) {
                            console.error('Client search:', err);
                        }
                    }, 250);
                });

                // =========================
                // Расчёт итогов
                // =========================
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
                    let totalPrice = 0, totalDuration = 0;
                    const selectedIds = [], selectedNames = [];
                    const useFixed = isFixedPriceCheckbox.checked;
                    const customMinutes = parseInt(customDurationInput.value, 10) || 0;
                    const hasCustom = customDurationToggle.checked && customMinutes > 0;
                    let sumPrice = 0;

                    serviceCheckboxes.forEach(cb => {
                        if (!cb.checked) return;
                        const price = parseFloat(cb.dataset.price) || 0;
                        const duration = parseInt(cb.dataset.duration, 10) || 0;
                        selectedIds.push(cb.value);
                        const label = cb.closest('label');
                        if (label) {
                            const name = label.querySelector('.font-medium')?.textContent.trim();
                            if (name) selectedNames.push(name);
                        }
                        if (!useFixed && hasCustom) sumPrice += price;
                        else { totalPrice += price; totalDuration += duration; }
                    });

                    if (!useFixed && hasCustom) {
                        totalPrice = sumPrice * customMinutes;
                        totalDuration = customMinutes;
                    }

                    totalPriceInput.value = totalPrice.toFixed(2);
                    totalDurationInput.value = totalDuration;
                    serviceIdsInput.value = JSON.stringify(selectedIds);
                    let summaryText = `Итого: ${totalPrice.toFixed(2)} ₽ · ${totalDuration} мин`;
                    if (!useFixed && hasCustom) summaryText += ' (не фиксированная)';
                    totalSummary.textContent = summaryText;
                    placeholder.textContent = selectedIds.length > 0 ? selectedNames.join(', ') : 'Выберите услуги';
                    recalculateEndTime();
                }

                serviceCheckboxes.forEach(cb => cb.addEventListener('change', updateTotal));
                isFixedPriceCheckbox.addEventListener('change', updateTotal);

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
                    } else endInput.value = '';
                }

                async function checkOverlap() {
                    if (!navigator.onLine) { overlapMessage.classList.add('hidden'); submitButton.disabled = false; return; }
                    const startValue = startInput.value;
                    if (!startValue) { overlapMessage.classList.add('hidden'); submitButton.disabled = false; return; }
                    const duration = getEffectiveDuration();
                    if (!duration) return;
                    const startDate = parseDateTime(startValue);
                    if (!startDate) return;
                    const endDate = new Date(startDate.getTime() + duration * 60000);
                    const endValue = formatDateTime(endDate);

                    try {
                        const response = await fetch('{{ route('appointments.check-overlap') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ start_time: startValue, end_time: endValue })
                        });
                        if (!response.ok) return;
                        const data = await response.json();
                        if (data.available) { overlapMessage.classList.add('hidden'); submitButton.disabled = false; }
                        else { overlapMessage.classList.remove('hidden'); submitButton.disabled = true; }
                    } catch (error) { overlapMessage.classList.add('hidden'); submitButton.disabled = false; }
                }

                async function loadAvailableSlots() {
                    if (!navigator.onLine) { slotsContainer.classList.add('hidden'); return; }
                    if (!denseModeCheckbox.checked) { slotsContainer.classList.add('hidden'); return; }
                    const firstChecked = Array.from(serviceCheckboxes).find(cb => cb.checked);
                    if (!firstChecked) return;
                    const serviceId = firstChecked.value;
                    const dateValue = startDateInput.value;
                    if (!serviceId || !dateValue) return;
                    let url = `{{ route('appointments.available-slots') }}?service_id=${serviceId}&date=${dateValue}`;
                    const duration = getEffectiveDuration();
                    if (duration) url += `&duration=${duration}`;

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
                                btn.addEventListener('click', function () {
                                    if (activeSlotButton) {
                                        activeSlotButton.classList.remove('bg-indigo-600', 'text-white');
                                        activeSlotButton.classList.add('bg-gray-100', 'text-gray-800');
                                    }
                                    btn.classList.remove('bg-gray-100', 'text-gray-800');
                                    btn.classList.add('bg-indigo-600', 'text-white');
                                    activeSlotButton = btn;
                                    const [datePart, timePart] = slot.start.split(' ');
                                    startDateInput.value = datePart;
                                    startTimeInput.value = timePart.slice(0, 5);
                                    combineDateTime();
                                    endInput.value = slot.end;
                                    recalculateEndTime(); checkOverlap();
                                });
                                slotsDiv.appendChild(btn);
                            });
                            slotsContainer.classList.remove('hidden');
                        } else slotsContainer.classList.add('hidden');
                    } catch (error) { slotsContainer.classList.add('hidden'); }
                }

                customDurationToggle.addEventListener('change', () => {
                    if (customDurationToggle.checked) {
                        customDurationContainer.classList.remove('hidden');
                        if (!customDurationInput.value) customDurationInput.value = getEffectiveDuration() || '';
                    } else {
                        customDurationContainer.classList.add('hidden');
                        customDurationInput.value = '';
                    }
                    updateTotal(); recalculateEndTime(); checkOverlap(); loadAvailableSlots();
                });

                customDurationInput.addEventListener('input', updateTotal);
                customDurationInput.addEventListener('change', () => {
                    updateTotal(); recalculateEndTime(); checkOverlap(); loadAvailableSlots();
                });

                // =========================
                // Отправка формы
                // =========================
                document.getElementById('appointment-form').addEventListener('submit', async function (e) {
                    e.preventDefault();
                    combineDateTime();

                    if (!clientIdInput.value) {
                        alert('Выберите клиента');
                        return;
                    }

                    const selectedIds = Array.from(serviceCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
                    if (selectedIds.length === 0) { alert('Выберите хотя бы одну услугу'); return; }
                    if (!startInput.value) { alert('Укажите дату и время начала'); return; }

                    const data = {
                        _token: '{{ csrf_token() }}',
                        client_id: clientIdInput.value || null,
                        service_ids: JSON.stringify(selectedIds),
                        total_price: totalPriceInput.value,
                        total_duration: totalDurationInput.value,
                        client_name: clientNameInput.value,
                        client_phone: clientPhoneInput.value,
                        client_email: clientEmailInput.value,
                        start_time: startInput.value,
                        end_time: endInput.value,
                        status: document.getElementById('status').value,
                        notes: document.getElementById('notes').value,
                        custom_duration: customDurationInput.value || '',
                        is_fixed_price: isFixedPriceCheckbox.checked ? 1 : 0,
                    };

                    try {
                        const response = await fetch(this.action, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify(data)
                        });
                        if (response.ok) {
                            window.location.href = '{{ route('appointments.index') }}';
                        } else {
                            const err = await response.json();
                            if (err.errors) alert(Object.values(err.errors).flat().join('\n'));
                            else await saveOffline(data);
                        }
                    } catch (error) { await saveOffline(data); }
                });

                async function saveOffline(data) {
                    try {
                        const db = await openDatabase();
                        const tx = db.transaction('pendingAppointments', 'readwrite');
                        tx.objectStore('pendingAppointments').add(data);
                        await tx.complete;
                        alert('Нет интернета. Запись сохранена локально.');
                        window.location.href = '{{ route('appointments.index') }}';
                    } catch (error) { alert('Не удалось сохранить запись офлайн.'); }
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

                updateTotal();
                if (denseModeCheckbox.checked) loadAvailableSlots();
            });
        </script>
    @endpush
</x-app-layout>
