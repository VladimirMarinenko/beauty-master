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
        <div class="space-y-4">
            <!-- Имя и Email -->
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
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                               class="input-field" autocomplete="username">
                        @if($errors->get('email'))
                            <p class="text-red-500 text-sm mt-1">{{ $errors->first('email') }}</p>
                        @endif
                    </div>

                    <button type="submit" class="btn-primary">Сохранить</button>
                </form>
            </div>

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
</x-app-layout>
