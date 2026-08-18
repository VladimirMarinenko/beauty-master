<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Beauty Manager') }}</title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#6d5ae6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Beauty Manager">

    <!-- Air Datepicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/air-datepicker@3.5.3/air-datepicker.css">
    <script src="https://cdn.jsdelivr.net/npm/air-datepicker@3.5.3/air-datepicker.js"></script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --primary: #6d5ae6;
            --primary-dark: #4f3dcc;
            --primary-light: #8b7cf7;
            --bg: #faf9ff;
            --card: #ffffff;
            --text: #2a2542;
            --muted: #7a7593;
            --border: #e8e5f2;
            --shadow-soft: 0 8px 24px rgba(93, 78, 184, 0.08);
            --shadow-card: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        * {
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Figtree', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding-bottom: env(safe-area-inset-bottom);
        }

        .app-container {
            max-width: 520px;
            margin: 0 auto;
            padding: 16px;
        }

        .card {
            background: var(--card);
            border-radius: 20px;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border-radius: 14px;
            padding: 14px 20px;
            font-weight: 600;
            text-align: center;
            transition: background 0.2s, transform 0.1s;
            border: none;
            width: 100%;
            display: inline-block;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-primary:active {
            background: var(--primary-dark);
            transform: scale(0.98);
        }

        .btn-secondary {
            background: #f1f0ff;
            color: var(--primary);
            border-radius: 14px;
            padding: 14px 20px;
            font-weight: 600;
            text-align: center;
            transition: background 0.2s;
            border: none;
            width: 100%;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background: #e6e4ff;
        }

        .input-field {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fbfaff;
            padding: 12px 16px;
            font-size: 16px;
            outline: none;
            transition: border 0.2s, box-shadow 0.2s;
            color: var(--text);
        }
        .input-field:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(109, 90, 230, 0.1);
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text);
        }

        .bottom-nav {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid var(--border);
            padding-bottom: env(safe-area-inset-bottom);
        }
        .nav-item {
            color: var(--muted);
            transition: color 0.2s, background 0.2s;
            border-radius: 12px;
            margin: 4px;
        }
        .nav-item.active {
            color: var(--primary);
            background: #f1f0ff;
        }
        .nav-item:hover {
            color: var(--primary);
        }

        .push-button {
            position: fixed;
            top: 12px;
            right: 12px;
            background: var(--primary);
            color: white;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: var(--shadow-soft);
            border: none;
            z-index: 60;
            cursor: pointer;
        }
        .push-status {
            position: fixed;
            top: 48px;
            right: 12px;
            font-size: 11px;
            color: var(--muted);
            z-index: 60;
        }

        /* Air Datepicker кастомизация */
        .air-datepicker {
            font-family: 'Figtree', sans-serif;
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .air-datepicker--content {
            padding: 0.5rem;
        }
        .air-datepicker--pointer {
            display: none;
        }
        .air-datepicker--cell.-selected- {
            background: var(--primary);
            color: white;
        }
        .air-datepicker--cell.-current- {
            color: var(--primary);
            font-weight: 600;
        }
        .air-datepicker--cell.-selected-.-current- {
            background: var(--primary-dark);
        }
        .air-datepicker--cell.-selected-.-focus- {
            background: var(--primary);
        }
        .air-datepicker--cell.-day-:hover,
        .air-datepicker--cell.-month-:hover,
        .air-datepicker--cell.-year-:hover {
            background: #eef2ff;
        }
        .air-datepicker--navigation {
            border-bottom: 1px solid var(--border);
        }
        .air-datepicker--nav-title {
            font-weight: 600;
        }
        .air-datepicker--time {
            background: #f9fafb;
            border-top: 1px solid var(--border);
        }
        .air-datepicker--time-sliders {
            padding: 0.5rem;
        }
        .air-datepicker--time-input {
            background: transparent;
            border: none;
            font-size: 1rem;
            text-align: center;
        }
    </style>
</head>
<body class="font-sans antialiased">
<div class="min-h-screen" style="background: var(--bg);">
    <!-- Page Heading -->
    @isset($header)
        <header class="bg-white shadow-sm" style="border-bottom: 1px solid var(--border);">
            <div class="app-container py-4">
                {{ $header }}
            </div>
        </header>
@endisset

<!-- Page Content -->
    <main class="pb-24" style="padding-bottom: calc(6rem + env(safe-area-inset-bottom));">
        {{ $slot }}
    </main>

    <!-- Push-уведомления -->
    <button id="push-subscribe-btn" class="push-button" style="display: none;">
        Включить уведомления
    </button>
    <span id="push-status" class="push-status"></span>

    <!-- Нижняя навигация -->
    <nav class="bottom-nav fixed bottom-0 left-0 right-0 z-50">
        <div class="grid grid-cols-4 h-16">
            <a href="{{ route('appointments.index') }}"
               class="nav-item flex flex-col items-center justify-center {{ request()->routeIs('appointments.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="text-xs mt-1">Календарь</span>
            </a>
            <a href="{{ route('transactions.index') }}"
               class="nav-item flex flex-col items-center justify-center {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-xs mt-1">Финансы</span>
            </a>
            <a href="{{ route('services.index') }}"
               class="nav-item flex flex-col items-center justify-center {{ request()->routeIs('services.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="text-xs mt-1">Услуги</span>
            </a>
            <a href="{{ route('profile.edit') }}"
               class="nav-item flex flex-col items-center justify-center {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="text-xs mt-1">Профиль</span>
            </a>
        </div>
    </nav>
</div>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then((registration) => {
                    console.log('SW зарегистрирован:', registration.scope);
                })
                .catch((error) => {
                    console.log('Ошибка регистрации SW:', error);
                });
        });
    }
</script>

@include('push.subscribe')
@stack('scripts')

</body>
</html>
