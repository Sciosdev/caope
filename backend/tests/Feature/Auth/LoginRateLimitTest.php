<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }
}
