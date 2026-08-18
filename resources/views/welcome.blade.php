<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Beauty Manager') }}</title>
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
        .welcome-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 8px 30px rgba(93, 78, 184, 0.08);
            max-width: 400px;
            width: 100%;
            padding: 40px 28px;
            text-align: center;
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
            margin: 0 auto 20px;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        p {
            color: #7a7593;
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 28px;
        }
        .btn {
            display: inline-block;
            padding: 14px 24px;
            border-radius: 14px;
            background: #6d5ae6;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: background 0.2s, transform 0.1s;
        }
        .btn:hover {
            background: #4f3dcc;
        }
        .btn:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>
<div class="welcome-card">
    <div class="logo">B</div>
    <h1>{{ config('app.name', 'Beauty Manager') }}</h1>
    <p>Управляйте записями, финансами и клиентами вашего бьюти-бизнеса в одном месте.</p>
    @auth
        <a href="{{ route('appointments.index') }}" class="btn">Перейти в приложение</a>
    @else
        <a href="{{ route('login') }}" class="btn">Войти</a>
    @endauth
</div>
</body>
</html>
