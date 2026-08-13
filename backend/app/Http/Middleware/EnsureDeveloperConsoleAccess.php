<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeveloperConsoleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('developer_console.enabled'), 404);

        $allowedIps = config('developer_console.allowed_ips', []);

        if (is_array($allowedIps) && $allowedIps !== []) {
            abort_unless(in_array($request->ip(), $allowedIps, true), 403);
        }

        return $next($request);
    }
}
