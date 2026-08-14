<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu cuenta está deshabilitada.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta está deshabilitada. Solicita activación al administrador.',
            ]);
        }

        return $next($request);
    }
}
