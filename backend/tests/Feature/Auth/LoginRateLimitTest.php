<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_threshold(): void
    {
        $user = User::factory()->create();

        $limiterKey = sprintf('login|%s|127.0.0.1', strtolower($user->email));
        RateLimiter::clear($limiterKey);

        try {
            Carbon::setTestNow($now = now());

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $response = $this->from(route('login'))->post('/login', [
                    'email' => $user->email,
                    'password' => 'incorrect-password',
                ]);

                $response->assertStatus(302);
                $response->assertSessionHasErrors('email');
            }

            $response = $this->from(route('login'))->post('/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ]);

            $response->assertStatus(429);

            Carbon::setTestNow($now->copy()->addSeconds(61));

            $response = $this->from(route('login'))->post('/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ]);

            $response->assertStatus(302);
            $response->assertSessionHasErrors('email');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_login_rate_limit_is_scoped_per_ip(): void
    {
        $user = User::factory()->create();

        $limiterKey = sprintf('login|%s|127.0.0.1', strtolower($user->email));
        RateLimiter::clear($limiterKey);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->from(route('login'))->post('/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ]);

            $response->assertStatus(302);
            $response->assertSessionHasErrors('email');
        }

        $this->from(route('login'))->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->from(route('login'))
            ->post('/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('email');
    }

    public function test_login_rate_limit_is_scoped_per_email(): void
    {
        $user = User::factory()->create();

        $limiterKey = sprintf('login|%s|127.0.0.1', strtolower($user->email));
        RateLimiter::clear($limiterKey);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->from(route('login'))->post('/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ]);

            $response->assertStatus(302);
            $response->assertSessionHasErrors('email');
        }

        $this->from(route('login'))->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertStatus(429);

        $this->from(route('login'))->post('/login', [
            'email' => 'another-'.$user->email,
            'password' => 'incorrect-password',
        ])->assertStatus(302)
            ->assertSessionHasErrors('email');
    }
}
