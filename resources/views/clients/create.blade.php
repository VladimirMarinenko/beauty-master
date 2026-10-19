<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">{{ __('Добавить клиента') }}</h2>
            <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-gray-700">← Назад</a>
        </div>
    </x-slot>

    <div class="app-container">
        <div class="card p-5">
            <form action="{{ route('clients.store') }}" method="POST">
                @csrf

                <div class="mb-5">
                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                        Имя и фамилия <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="input-field" placeholder="Например: Анна Иванова">
                    @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-5">
                    <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Телефон</label>
                    <input type="tel" name="phone" id="phone" value="{{ old('phone') }}"
                           class="input-field" placeholder="+7 (___) ___-__-__">
                </div>

                <div class="mb-5">
                    <label for="contact" class="block text-sm font-semibold text-gray-700 mb-2">
                        Соц. сети или почта
                    </label>
                    <input type="text" name="contact" id="contact" value="{{ old('contact') }}"
                           class="input-field" placeholder="Instagram, Telegram, email...">
                </div>

                <div class="mb-5">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_permanent" id="is_permanent" value="1"
                               class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                            {{ old('is_permanent') ? 'checked' : '' }}>
                        <span class="ml-3 text-sm font-medium text-gray-700">
                            Постоянный клиент
                        </span>
                    </label>
                </div>

                <div id="services-block" class="mb-6 hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Какие услуги постоянно делает
                    </label>
                    <div class="space-y-2 max-h-72 overflow-y-auto border border-gray-200 rounded-xl p-3">
                        @foreach($services as $service)
                            <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                                <input type="checkbox" name="service_ids[]" value="{{ $service->id }}"
                                       class="h-5 w-5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                                    {{ (is_array(old('service_ids')) && in_array($service->id, old('service_ids'))) ? 'checked' : '' }}>
                                <span class="flex-1">
                                    <span class="font-medium text-sm">{{ $service->name }}</span>
                                    <span class="text-xs text-gray-600 block">{{ number_format($service->price, 2) }} ₽ · {{ $service->duration }} мин</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if($services->isEmpty())
                        <p class="text-sm text-gray-500 mt-2">Сначала добавьте услуги.</p>
                    @endif
                </div>

                <button type="submit" class="btn-primary">Сохранить клиента</button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const checkbox = document.getElementById('is_permanent');
                const block = document.getElementById('services-block');

                function toggle() {
                    if (checkbox.checked) {
                        block.classList.remove('hidden');
                    } else {
                        block.classList.add('hidden');
                    }
                }

                checkbox.addEventListener('change', toggle);
                toggle();
            });
        </script>
    @endpush
</x-app-layout>
