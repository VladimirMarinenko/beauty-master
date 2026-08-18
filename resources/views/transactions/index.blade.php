<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ __('Финансы') }}
            </h2>
            <a href="{{ route('reports.index') }}" class="text-sm font-semibold" style="color: var(--primary);">
                Отчёты
            </a>
        </div>
    </x-slot>

    <div class="app-container">
        <!-- Статистика -->
        <div class="card p-4 mb-4">
            <h3 class="text-base font-semibold mb-3" style="color: var(--text);">Общая статистика</h3>
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-green-50 rounded-xl p-3 text-center">
                    <p class="text-xs text-green-700 font-medium">Доходы</p>
                    <p class="text-sm font-bold text-green-800 mt-1">{{ number_format($totalIncome, 0) }} ₽</p>
                </div>
                <div class="bg-red-50 rounded-xl p-3 text-center">
                    <p class="text-xs text-red-700 font-medium">Расходы</p>
                    <p class="text-sm font-bold text-red-800 mt-1">{{ number_format($totalExpense, 0) }} ₽</p>
                </div>
                <div class="bg-indigo-50 rounded-xl p-3 text-center">
                    <p class="text-xs text-indigo-700 font-medium">Прибыль</p>
                    <p class="text-sm font-bold text-indigo-800 mt-1">{{ number_format($netProfit, 0) }} ₽</p>
                </div>
            </div>
        </div>

        <!-- Кнопки -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <a href="{{ route('transactions.create') }}" class="btn-primary">
                + Добавить
            </a>
            <a href="{{ route('reports.index') }}" class="btn-secondary">
                Отчёты
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 text-green-600 text-sm font-medium bg-green-50 px-4 py-3 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

    <!-- Фильтры -->
        <form method="GET" action="{{ route('transactions.index') }}" class="card p-4 mb-4">
            <div class="flex flex-wrap gap-2 items-end">
                <div class="flex-1 min-w-[45%]">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--muted);">Тип</label>
                    <select name="type" class="input-field">
                        <option value="">Все типы</option>
                        <option value="income" {{ request('type') == 'income' ? 'selected' : '' }}>Доход</option>
                        <option value="expense" {{ request('type') == 'expense' ? 'selected' : '' }}>Расход</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[45%]">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--muted);">Период</label>
                    <select name="period" class="input-field">
                        <option value="">Все периоды</option>
                        <option value="week" {{ request('period') == 'week' ? 'selected' : '' }}>Неделя</option>
                        <option value="month" {{ request('period') == 'month' ? 'selected' : '' }}>Месяц</option>
                        <option value="year" {{ request('period') == 'year' ? 'selected' : '' }}>Год</option>
                    </select>
                </div>
                <button type="submit" class="btn-secondary" style="width: auto; padding: 10px 16px;">
                    Применить
                </button>
            </div>
        </form>

        <!-- Список транзакций -->
        @if($transactions->count() > 0)
            <div class="space-y-3">
                @foreach($transactions as $transaction)
                    <div class="card p-4">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    @if($transaction->type == 'income')
                                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500"></span>
                                        <span class="text-green-600 text-sm font-semibold">Доход</span>
                                    @else
                                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span>
                                        <span class="text-red-600 text-sm font-semibold">Расход</span>
                                    @endif
                                    <span class="text-xs" style="color: var(--muted);">{{ $transaction->transaction_date->format('d.m.Y H:i') }}</span>
                                </div>
                                <p class="text-lg font-bold mt-1" style="color: var(--text);">{{ number_format($transaction->amount, 2) }} ₽</p>
                                @if($transaction->category)
                                    <p class="text-xs" style="color: var(--muted);">{{ $transaction->category }}</p>
                                @endif
                                @if($transaction->description)
                                    <p class="text-xs mt-0.5" style="color: var(--muted);">{{ $transaction->description }}</p>
                                @endif
                            </div>
                            <div class="flex flex-col items-end space-y-3 ml-4">
                                <a href="{{ route('transactions.edit', $transaction) }}" class="text-sm font-semibold" style="color: var(--primary);">
                                    Изм.
                                </a>
                                <form action="{{ route('transactions.destroy', $transaction) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-500" onclick="return confirm('Удалить транзакцию?')">
                                        Удал.
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">
                {{ $transactions->links() }}
            </div>
        @else
            <div class="card p-8 text-center">
                <div class="text-4xl mb-3">💰</div>
                <p class="font-medium" style="color: var(--muted);">Транзакций нет.</p>
            </div>
        @endif
    </div>
</x-app-layout>
