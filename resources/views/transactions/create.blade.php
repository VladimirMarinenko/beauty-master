<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ __('Добавить транзакцию') }}
            </h2>
            <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-gray-700">
                ← Назад
            </a>
        </div>
    </x-slot>

    <div class="app-container">
        <div class="card p-5">
            <form action="{{ route('transactions.store') }}" method="POST">
                @csrf

                <div class="mb-5">
                    <label for="type" class="block text-sm font-semibold text-gray-700 mb-2">Тип</label>
                    <select name="type" id="type" required class="input-field">
                        <option value="income">Доход</option>
                        <option value="expense">Расход</option>
                    </select>
                </div>

                <div class="mb-5">
                    <label for="amount" class="block text-sm font-semibold text-gray-700 mb-2">Сумма (₽)</label>
                    <input type="number" step="0.01" min="0" name="amount" id="amount" value="{{ old('amount') }}" required
                           class="input-field" placeholder="1500">
                </div>

                <div class="mb-5">
                    <label for="transaction_date" class="block text-sm font-semibold text-gray-700 mb-2">Дата и время</label>
                    <input type="text" name="transaction_date" id="transaction_date"
                           value="{{ old('transaction_date', now()->format('Y-m-d H:i')) }}" required
                           class="input-field datepicker-datetime cursor-pointer"
                           placeholder="Выберите дату и время">
                </div>

                <div class="mb-5">
                    <label for="category" class="block text-sm font-semibold text-gray-700 mb-2">Категория</label>
                    <input type="text" name="category" id="category" value="{{ old('category') }}"
                           class="input-field" placeholder="Например: аренда, реклама, материалы">
                </div>

                <div class="mb-5">
                    <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Описание</label>
                    <textarea name="description" id="description" rows="4"
                              class="input-field" placeholder="Дополнительная информация...">{{ old('description') }}</textarea>
                </div>

                <div class="mb-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="is_recurring" name="is_recurring" value="1"
                               class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                            {{ old('is_recurring') ? 'checked' : '' }}>
                        <span class="ml-3 text-sm font-medium text-gray-700">
                            Ежемесячный платёж
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn-primary">
                    Сохранить транзакцию
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
                    timeFormat: 'HH:mm',
                    firstDay: 1
                };

                new AirDatepicker(document.getElementById('transaction_date'), {
                    locale: ruLocale,
                    timepicker: true,
                    dateFormat: 'yyyy-MM-dd',
                    timeFormat: 'HH:mm',
                    minutesStep: 1,
                    buttons: ['today', 'clear'],
                    onSelect: function({date}) {
                        if (date) {
                            const d = date;
                            const year = d.getFullYear();
                            const month = String(d.getMonth() + 1).padStart(2, '0');
                            const day = String(d.getDate()).padStart(2, '0');
                            const hours = String(d.getHours()).padStart(2, '0');
                            const minutes = String(d.getMinutes()).padStart(2, '0');
                            document.getElementById('transaction_date').value = `${year}-${month}-${day} ${hours}:${minutes}`;
                        }
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
