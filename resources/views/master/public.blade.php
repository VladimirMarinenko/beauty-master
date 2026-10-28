<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide" style="color: var(--muted); letter-spacing: 0.5px;">
                    Запись на приём
                </p>
                <h2 class="page-title mt-1">Ваш мастер — {{ $master->name }}</h2>
            </div>
        </div>
    </x-slot>

    <div class="app-container">
        {{-- Карточка мастера --}}
        <div class="card p-5 mb-4" style="text-align: center;">
            <div style="
                width: 72px;
                height: 72px;
                border-radius: 20px;
                background: var(--primary);
                color: white;
                font-size: 32px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 12px;
            ">
                {{ mb_strtoupper(mb_substr($master->name, 0, 1)) }}
            </div>
            <div class="font-semibold text-base" style="color: var(--text);">
                {{ $master->name }}
            </div>
            <div class="text-xs mt-1" style="color: var(--muted);">
                Мастер по красоте
            </div>
        </div>

        {{-- Плашка «вы уже залогинены» --}}
        @if($loggedUser && $loggedUser->isClient())
            <div class="card p-4 mb-4" style="border-color: var(--primary-light);">
                @if($alreadyAttached)
                    <p style="color: var(--text);">
                        Вы уже привязаны к мастеру <strong>{{ $master->name }}</strong>.
                    </p>
                    <a href="{{ route('me.index') }}" class="btn-primary mt-3" style="display: inline-block; width: auto;">
                        Перейти в кабинет
                    </a>
                @else
                    <p style="color: var(--text);">
                        Вы вошли как <strong>{{ $loggedUser->name }}</strong>.
                    </p>
                    <p class="text-sm mt-1" style="color: var(--muted);">
                        Привязаться к мастеру {{ $master->name }}?
                    </p>
                    <div class="mt-3" style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <form method="POST" action="{{ route('master.attach', ['slug' => $master->slug, 'token' => $master->invite_token]) }}">
                            @csrf
                            <button type="submit" class="btn-primary" style="width: auto;">
                                Привязаться
                            </button>
                        </form>
                        <a href="{{ route('me.index') }}" class="btn-secondary" style="width: auto;">
                            Отмена
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Список услуг --}}
        <div class="card p-4">
            <h3 class="text-base font-semibold mb-3" style="color: var(--text);">
                Услуги
            </h3>

            @if($services->isEmpty())
                <p style="color: var(--muted);">Мастер пока не добавил услуги.</p>
            @else
                <div class="space-y-2">
                    @foreach($services as $service)
                        <div class="flex justify-between items-center py-2 border-b border-gray-100 gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium" style="color: var(--text);">
                                    {{ $service->name }}
                                </div>
                                <div class="text-xs mt-0.5" style="color: var(--muted);">
                                    {{ $service->duration }} мин
                                </div>
                            </div>
                            <div class="text-sm font-semibold flex-shrink-0" style="color: var(--primary);">
                                {{ number_format($service->price, 0, '.', ' ') }} ₽
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Форма регистрации — только для гостя --}}
        @guest
            <div class="card p-4 mt-4">
                <h3 class="text-base font-semibold mb-1" style="color: var(--text);">
                    Записаться к мастеру {{ $master->name }}
                </h3>
                <p class="text-xs mb-4" style="color: var(--muted);">
                    Зарегистрируйтесь — и вы сразу окажетесь привязаны к мастеру
                </p>

                <form method="POST" action="{{ route('master.register', ['slug' => $master->slug, 'token' => $master->invite_token]) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="reg-name" class="block text-sm font-semibold mb-2" style="color: #475569;">
                            Имя <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="reg-name" name="name" value="{{ old('name') }}"
                               class="input-field" placeholder="Как к вам обращаться" required autofocus>
                        @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="reg-phone" class="block text-sm font-semibold mb-2" style="color: #475569;">
                            Телефон <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" id="reg-phone" name="phone" value="{{ old('phone') }}"
                               class="input-field" placeholder="+7 (___) ___-__-__" required autocomplete="tel">
                        @error('phone')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="reg-password" class="block text-sm font-semibold mb-2" style="color: #475569;">
                            Пароль <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="reg-password" name="password"
                               class="input-field" placeholder="Минимум 8 символов" required autocomplete="new-password">
                        @error('password')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="reg-password-confirm" class="block text-sm font-semibold mb-2" style="color: #475569;">
                            Подтверждение пароля <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="reg-password-confirm" name="password_confirmation"
                               class="input-field" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-primary">
                        Зарегистрироваться и записаться
                    </button>
                </form>

                <p class="text-xs mt-4 text-center" style="color: var(--muted);">
                    Уже есть аккаунт?
                    <a href="{{ route('login') }}" style="color: var(--primary); font-weight: 600;">
                        Войти
                    </a>
                </p>
            </div>
        @endguest
    </div>
</x-app-layout>
