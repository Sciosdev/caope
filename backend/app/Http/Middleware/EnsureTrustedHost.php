<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EnsureTrustedHost
{
    private const PUBLIC_HOSTS = [
        'caope.ayudafesi.com',
        'xocoyotzin.iztacala.unam.mx',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        Request::setTrustedHosts($this->trustedHostPatterns());

        try {
            $request->getHost();

            return $next($request);
        } catch (SuspiciousOperationException) {
            throw new BadRequestHttpException('Invalid Host header.');
        } finally {
            // Symfony stores this allowlist statically. Reset it after the
            // complete request so long-running workers and tests cannot leak
            // one request's host policy into out-of-band URL generation.
            Request::setTrustedHosts([]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function trustedHostPatterns(): array
    {
        $hosts = config()->has('security.trusted_hosts')
            ? config('security.trusted_hosts')
            : $this->transitionHosts();

        if (! is_array($hosts) || $hosts === []) {
            throw new LogicException('At least one trusted host must be configured.');
        }

        return array_map(
            static fn (mixed $host): string => '^'.preg_quote(strtolower(trim((string) $host)), '{').'$',
            $hosts,
        );
    }

    /**
     * The first deployment can briefly run the new middleware with the prior
     * cached configuration, which does not contain config/security.php yet.
     * Keep that transition fail-closed without taking the real hosts offline.
     *
     * @return list<string>
     */
    private function transitionHosts(): array
    {
        return app()->environment('local', 'testing')
            ? [...self::PUBLIC_HOSTS, 'localhost', '127.0.0.1', '[::1]']
            : self::PUBLIC_HOSTS;
    }
}
