<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePapsIsApproved
{
    public function handle(Request $request, Closure $next, string ...$fallbackRoles): Response
    {
        $user = $request->user();

        if ($user?->hasRole('paps')
            && ! $user->isApprovedPaps()
            && ! $user->hasAnyRole($fallbackRoles)) {
            abort(403);
        }

        return $next($request);
    }
}
