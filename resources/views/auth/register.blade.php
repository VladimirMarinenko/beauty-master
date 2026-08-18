<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Beauty Manager') }} — Регистрация</title>
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
        .register-card {
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
        input[type="text"],
        input[type="email"],
        input[type="password"] {
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
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="register-card">
    <div class="logo">B</div>
    <h1>Регистрация</h1>
    <p class="subtitle">Создайте аккаунт в {{ config('app.name', 'Beauty Manager') }}</p>

    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-group">
            <label for="name">Имя</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
        </div>

        <div class="form-group">
            <label for="password_confirmation">Подтверждение пароля</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn">Зарегистрироваться</button>

        <div class="flex-between">
            <a href="{{ route('login') }}" class="link">Уже есть аккаунт? Войти</a>
        </div>
    </form>
</div>
</body>
</html>
