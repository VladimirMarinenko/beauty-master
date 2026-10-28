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

        $user = $request->user();

        // 1. Проверяем intended — куда пользователь шёл до логина
        $intended = $request->session()->pull('url.intended');

        if ($intended) {
            $isMasterUrl = str_starts_with($intended, url('/appointments'))
                || str_starts_with($intended, url('/clients'))
                || str_starts_with($intended, url('/services'))
                || str_starts_with($intended, url('/reports'))
                || str_starts_with($intended, url('/transactions'))
                || str_starts_with($intended, url('/attendance'))
                || str_starts_with($intended, url('/invite'));

            $isClientUrl = str_starts_with($intended, url('/me'));

            $mismatch = ($user->isClient() && !$isClientUrl)
                || ($user->isMaster() && $isClientUrl);

            if (!$mismatch) {
                return redirect()->to($intended);
            }
        }

        // 2. Клиент — всегда в свой кабинет
        if ($user->isClient()) {
            return redirect()->route('me.index');
        }

        // 3. Мастер: если есть просроченные planned-записи → на подтверждение
        $hasPending = $user->appointments()
            ->where('status', 'planned')
            ->where('start_time', '<', now())
            ->exists();

        if ($hasPending) {
            return redirect()->route('attendance.index');
        }

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
