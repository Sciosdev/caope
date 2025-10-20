<?php

namespace App\Listeners;

use App\Services\AuthEventTimelineRecorder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class AuthEventSubscriber
{
    public function __construct(
        protected AuthEventTimelineRecorder $timelineRecorder
    ) {
    }

    public function onLogin(Login $event): void
    {
        $context = $this->buildContext([
            'user_id' => $event->user?->getAuthIdentifier(),
            'guard' => $event->guard,
            'remember' => $event->remember,
        ]);

        Log::info('Usuario autenticado correctamente.', $context);

        $this->timelineRecorder->recordLogin($event->user, $context);
    }

    public function onLogout(Logout $event): void
    {
        $context = $this->buildContext([
            'user_id' => $event->user?->getAuthIdentifier(),
            'guard' => $event->guard,
        ]);

        Log::info('El usuario cerró sesión.', $context);

        $this->timelineRecorder->recordLogout($event->user, $context);
    }

    public function onFailed(Failed $event): void
    {
        $sanitized = Arr::except($event->credentials, ['password', 'password_confirmation']);

        $context = $this->buildContext([
            'guard' => $event->guard,
            'user_id' => $event->user?->getAuthIdentifier(),
            'credentials' => $sanitized,
        ]);

        Log::info('Intento de autenticación fallido.', $context);

        $this->timelineRecorder->recordFailed($event->user, $context);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'onLogin']);
        $events->listen(Logout::class, [self::class, 'onLogout']);
        $events->listen(Failed::class, [self::class, 'onFailed']);
    }

    protected function buildContext(array $context): array
    {
        $request = request();

        if ($request) {
            $context = array_merge([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], $context);
        }

        return $context;
    }
}
