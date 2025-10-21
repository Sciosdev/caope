<?php

namespace Tests\Feature\Smoke;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecuritySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_throttle_triggers_after_repeated_failures(): void
    {
        $user = User::factory()->create();

        $limiterKey = sprintf('login|%s|127.0.0.1', strtolower($user->email));
        RateLimiter::clear($limiterKey);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post('/login', [
                'email' => $user->email,
                'password' => 'invalid-password',
            ])->assertStatus(302);
        }

        $this->from(route('login'))->post('/login', [
            'email' => $user->email,
            'password' => 'invalid-password',
        ])->assertStatus(429);
    }

    public function test_env_file_is_not_publicly_accessible(): void
    {
        $response = $this->get('/.env');

        $response->assertNotFound();
        $response->assertDontSee('APP_KEY=');
        $response->assertDontSee('DB_PASSWORD=');
    }
}
