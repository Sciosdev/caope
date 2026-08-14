<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.datatables.net https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.datatables.net https://cdn.jsdelivr.net https://fonts.bunny.net; font-src 'self' data: https://fonts.bunny.net; img-src 'self' data: blob:; connect-src 'self'";

    private const PERMISSIONS_POLICY = 'camera=(), geolocation=(), microphone=()';

    private const REFERRER_POLICY = 'strict-origin-when-cross-origin';

    private const STRICT_TRANSPORT_SECURITY = 'max-age=31536000; includeSubDomains';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set(
                'Content-Security-Policy',
                (string) config('security.headers.content_security_policy', self::CONTENT_SECURITY_POLICY),
            );
        }
        $response->headers->set(
            'Permissions-Policy',
            (string) config('security.headers.permissions_policy', self::PERMISSIONS_POLICY),
        );
        $response->headers->set(
            'Referrer-Policy',
            (string) config('security.headers.referrer_policy', self::REFERRER_POLICY),
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                (string) config(
                    'security.headers.strict_transport_security',
                    self::STRICT_TRANSPORT_SECURITY,
                ),
            );
        }

        return $response;
    }
}
