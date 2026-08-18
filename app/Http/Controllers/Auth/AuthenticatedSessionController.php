<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Получаем текущего пользователя
        $user = $request->user();

        // Проверяем, есть ли прошедшие записи со статусом "planned"
        $hasPending = $user->appointments()
            ->where('status', 'planned')
            ->where('start_time', '<', now())
            ->exists();

        // Если есть такие записи — отправляем на страницу подтверждения
        if ($hasPending) {
            return redirect()->route('attendance.index');
        }

        // Иначе на календарь
        return redirect()->route('appointments.index');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
