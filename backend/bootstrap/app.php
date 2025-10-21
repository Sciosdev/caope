<?php

use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$app->booted(function () use ($app): void {
    $limiter = $app->make(CacheRateLimiter::class);

    $limiter->for('login', function (Request $request) {
        $email = strtolower((string) $request->input('email'));
        $identifier = $email !== '' ? $email.'|'.$request->ip() : $request->ip();

        return Limit::perMinute(5)->by($identifier);
    });

    $limiter->for('auth-general', function (Request $request) {
        return Limit::perMinute(10)->by($request->ip());
    });

    $limiter->for('uploads.consentimientos', function (Request $request) {
        $userKey = (string) optional($request->user())->getAuthIdentifier();
        $identifier = $userKey !== '' ? $userKey : $request->ip();

        return Limit::perMinutes(10, 5)->by('consentimientos|'.$identifier);
    });

    $limiter->for('uploads.anexos', function (Request $request) {
        $userKey = (string) optional($request->user())->getAuthIdentifier();
        $identifier = $userKey !== '' ? $userKey : $request->ip();

        return Limit::perMinutes(10, 8)->by('anexos|'.$identifier);
    });
});

return $app;
