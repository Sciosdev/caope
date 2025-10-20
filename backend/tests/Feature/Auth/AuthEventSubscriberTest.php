<?php

namespace Tests\Feature\Auth;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthEventSubscriberTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_event_is_logged_and_persisted_when_enabled(): void
    {
        config()->set('auth-events.timeline.enabled', true);

        Log::spy();

        $user = User::factory()->create();
        $expediente = Expediente::factory()->create([
            'creado_por' => $user->getKey(),
        ]);

        event(new Login('web', $user, false));

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($user): bool {
                return $message === 'Usuario autenticado correctamente.'
                    && $context['user_id'] === $user->getKey()
                    && $context['guard'] === 'web';
            });

        $this->assertDatabaseHas('timeline_eventos', [
            'actor_id' => $user->getKey(),
            'evento' => 'auth.login',
            'expediente_id' => $expediente->getKey(),
        ]);
    }

    public function test_logout_event_is_logged(): void
    {
        config()->set('auth-events.timeline.enabled', false);

        Log::spy();

        $user = User::factory()->create();

        event(new Logout('web', $user));

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'El usuario cerró sesión.'
                && $context['user_id'] === $user->getKey()
                && $context['guard'] === 'web'
            );

        $this->assertDatabaseCount('timeline_eventos', 0);
    }

    public function test_failed_event_logs_without_password(): void
    {
        Log::spy();

        $credentials = [
            'email' => 'example@example.com',
            'password' => 'secret',
        ];

        event(new Failed('web', null, $credentials));

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'Intento de autenticación fallido.'
                && $context['guard'] === 'web'
                && ! array_key_exists('password', $context['credentials'])
            );
    }
}
