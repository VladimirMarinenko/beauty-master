<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Beauty Manager') }} — Вход</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Figtree', sans-serif;
            background: #faf9ff;
            color: #2a2542;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 8px 30px rgba(93, 78, 184, 0.08);
            max-width: 400px;
            width: 100%;
            padding: 40px 28px;
            border: 1px solid #e8e5f2;
        }
        .logo {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: #6d5ae6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            margin: 0 auto 24px;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            text-align: center;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #7a7593;
            font-size: 14px;
            text-align: center;
            margin-bottom: 28px;
        }
        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            border: 1px solid #e8e5f2;
            border-radius: 14px;
            background: #fbfaff;
            padding: 14px 16px;
            font-size: 16px;
            outline: none;
            transition: border 0.2s, box-shadow 0.2s;
        }
        input:focus {
            border-color: #8b7cf7;
            box-shadow: 0 0 0 3px rgba(109, 90, 230, 0.1);
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            color: #475569;
        }
        .checkbox-label input {
            margin-right: 8px;
            width: 18px;
            height: 18px;
        }
        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            background: #6d5ae6;
            color: white;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .btn:hover {
            background: #4f3dcc;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .link {
            color: #6d5ae6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .link:hover {
            text-decoration: underline;
        }
        .error {
            color: #dc2626;
            font-size: 14px;
            margin-bottom: 20px;
            background: #fef2f2;
            padding: 12px;
            border-radius: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .text-center {
            text-align: center;
        }
        .mt-6 {
            margin-top: 24px;   /* ← отступ над «Зарегистрироваться» */
        }

        /* === Поле пароля с глазком === */
        .password-wrapper {
            position: relative;
            display: block;
        }
        .password-wrapper input {
            padding-right: 48px;   /* место под глазок */
        }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            padding: 0;
            margin: 0;
            cursor: pointer;
            color: #7a7593;
            border-radius: 8px;
            transition: color 0.15s, background 0.15s;
        }
        .password-toggle:hover,
        .password-toggle:active {
            color: #6d5ae6;
            background: rgba(109, 90, 230, 0.08);
        }
        .password-toggle svg {
            pointer-events: none;
            display: block;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="logo">B</div>
    <h1>Вход</h1>
    <p class="subtitle">Добро пожаловать в {{ config('app.name', 'Beauty Manager') }}</p>

    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <div class="password-wrapper">
                <input id="password" type="password" name="password" required autocomplete="current-password">
                <button type="button" class="password-toggle" id="password-toggle" aria-label="Показать пароль" tabindex="-1">
                    <!-- «Закрытый глаз» — по умолчанию (пароль скрыт) -->
                    <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.828M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.543 7a9.998 9.998 0 01-2.347 3.607M6.228 6.228A10.005 10.005 0 002.457 12c1.274 4.057 5.065 7 9.543 7a9.966 9.966 0 004.293-.958"/>
                    </svg>
                    <!-- «Открытый глаз» — когда пароль показан -->
                    <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                Запомнить меня
            </label>
        </div>

        <button type="submit" class="btn">Войти</button>

        <div class="text-center mt-6">
            <a href="{{ route('register') }}" class="link">Нет аккаунта? Зарегистрироваться</a>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('password-toggle');
        const input  = document.getElementById('password');
        const eyeOpen   = document.getElementById('eye-open');
        const eyeClosed = document.getElementById('eye-closed');

        if (!toggle || !input) return;

        toggle.addEventListener('click', function () {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            eyeOpen.style.display   = isPassword ? 'block' : 'none';
            eyeClosed.style.display = isPassword ? 'none'  : 'block';
            toggle.setAttribute('aria-label', isPassword ? 'Скрыть пароль' : 'Показать пароль');
        });
    });
</script>
</body>
</html>
