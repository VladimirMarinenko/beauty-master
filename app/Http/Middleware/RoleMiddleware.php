<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                abort(403, 'Доступ запрещён для вашей роли.');
            }

            $target = $user->isClient() ? 'me.index' : 'appointments.index';

            return redirect()->route($target)
                ->with('error', 'Вы были перенаправлены в свой раздел.');
        }

        return $next($request);
    }
}
