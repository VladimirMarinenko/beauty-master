<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">
                {{ __('Добавить услугу') }}
            </h2>
            <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-gray-700">
                ← Назад
            </a>
        </div>
    </x-slot>

    <div class="app-container">
        <div class="card p-5">
            <form action="{{ route('services.store') }}" method="POST">
                @csrf

                <div class="mb-5">
                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Название услуги</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="input-field" placeholder="Например: Шугаринг глубокое бикини">
                </div>

                <div class="mb-5">
                    <label for="price" class="block text-sm font-semibold text-gray-700 mb-2">Цена (₽)</label>
                    <input type="number" step="0.01" min="0" name="price" id="price" value="{{ old('price') }}" required
                           class="input-field" placeholder="1500">
                </div>

                <div class="mb-5">
                    <label for="duration" class="block text-sm font-semibold text-gray-700 mb-2">Длительность (минут)</label>
                    <input type="number" min="1" name="duration" id="duration" value="{{ old('duration') }}" required
                           class="input-field" placeholder="30">
                </div>

                <div class="mb-6">
                    <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Описание (необязательно)</label>
                    <textarea name="description" id="description" rows="4"
                              class="input-field" placeholder="Дополнительная информация...">{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="btn-primary">
                    Сохранить услугу
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
