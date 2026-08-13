<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentDeveloperPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        $timeout = (int) config('developer_console.password_timeout', 900);

        if ($confirmedAt === 0 || (time() - $confirmedAt) > $timeout) {
            return redirect()->guest(route('password.confirm'));
        }

        return $next($request);
    }
}
