<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ __('Отчёты') }}
            </h2>
        </div>
    </x-slot>

    <div class="app-container">
        <!-- Переключатель периода -->
        <div class="card p-4 mb-4">
            <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap gap-2 items-center">
                <select name="period" class="input-field" style="width: auto; flex: 1; min-width: 120px;">
                    <option value="week" {{ $period == 'week' ? 'selected' : '' }}>Неделя</option>
                    <option value="month" {{ $period == 'month' ? 'selected' : '' }}>Месяц</option>
                    <option value="year" {{ $period == 'year' ? 'selected' : '' }}>Год</option>
                </select>
                <button type="submit" class="btn-secondary" style="width: auto; padding: 10px 16px;">Показать</button>
            </form>
        </div>

        <!-- Средний чек и загрузка -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="card p-4 text-center">
                <p class="text-xs font-medium" style="color: var(--muted);">Средний чек</p>
                <p class="text-xl font-bold" style="color: var(--primary);">{{ number_format($averageCheck, 2) }} ₽</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-xs font-medium" style="color: var(--muted);">Записей за период</p>
                <p class="text-xl font-bold" style="color: var(--text);">{{ array_sum($appointmentsData) }}</p>
            </div>
        </div>

        <!-- График доходов и расходов -->
        <div class="card p-4 mb-4">
            <h3 class="text-base font-semibold mb-3" style="color: var(--text);">Доходы и расходы</h3>
            <canvas id="incomeExpenseChart" height="180"></canvas>
        </div>

        <!-- График загрузки по дням -->
        <div class="card p-4 mb-4">
            <h3 class="text-base font-semibold mb-3" style="color: var(--text);">Загрузка по дням</h3>
            <canvas id="appointmentsChart" height="180"></canvas>
        </div>

        <!-- Диаграмма категорий расходов -->
        @if($expenseCategories->isNotEmpty())
            <div class="card p-4 mb-4">
                <h3 class="text-base font-semibold mb-3" style="color: var(--text);">Расходы по категориям</h3>
                <canvas id="expenseCategoriesChart" height="180"></canvas>
            </div>
        @endif

    <!-- Отчёт по услугам -->
        @if($serviceReport->isNotEmpty())
            <div class="card p-4">
                <h3 class="text-base font-semibold mb-3" style="color: var(--text);">Выручка по услугам</h3>
                <div class="space-y-2">
                    @foreach($serviceReport as $item)
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm" style="color: var(--text);">{{ $item->name }}</span>
                            <span class="text-sm font-semibold" style="color: var(--muted);">{{ $item->count }} раз · {{ number_format($item->revenue, 2) }} ₽</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Доходы и расходы
                const incExpCtx = document.getElementById('incomeExpenseChart').getContext('2d');
                new Chart(incExpCtx, {
                    type: 'line',
                    data: {
                        labels: {!! json_encode($dates->map(fn($d) => Carbon\Carbon::parse($d)->format('d.m'))) !!},
                        datasets: [
                            {
                                label: 'Доходы',
                                data: {!! json_encode($incomeData) !!},
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16,185,129,0.1)',
                                tension: 0.3,
                                fill: true
                            },
                            {
                                label: 'Расходы',
                                data: {!! json_encode($expenseData) !!},
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239,68,68,0.1)',
                                tension: 0.3,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });

                // Загрузка по дням
                const appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
                new Chart(appointmentsCtx, {
                    type: 'bar',
                    data: {
                        labels: {!! json_encode($dates->map(fn($d) => Carbon\Carbon::parse($d)->format('d.m'))) !!},
                        datasets: [{
                            label: 'Количество записей',
                            data: {!! json_encode($appointmentsData) !!},
                            backgroundColor: '#6d5ae6',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } }
                        }
                    }
                });

                // Категории расходов
                @if($expenseCategories->isNotEmpty())
                const expCatCtx = document.getElementById('expenseCategoriesChart').getContext('2d');
                new Chart(expCatCtx, {
                    type: 'doughnut',
                    data: {
                        labels: {!! json_encode($expenseCategories->pluck('category')) !!},
                        datasets: [{
                            data: {!! json_encode($expenseCategories->pluck('total')) !!},
                            backgroundColor: ['#f59e0b', '#ef4444', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899'],
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
                @endif
            });
        </script>
    @endpush
</x-app-layout>
