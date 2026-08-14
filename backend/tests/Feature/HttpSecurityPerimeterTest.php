<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HttpSecurityPerimeterTest extends TestCase
{
    #[DataProvider('trustedHosts')]
    public function test_trusted_hosts_can_reach_an_application_subpath(string $host): void
    {
        $this->get("http://{$host}/login")
            ->assertOk();
    }

    public function test_an_untrusted_host_is_rejected_before_the_request_is_handled(): void
    {
        $this->get('http://attacker.example/login')
            ->assertBadRequest();
    }

    public function test_static_host_state_is_cleared_after_each_request(): void
    {
        $this->get('http://caope.ayudafesi.com/up')->assertOk();

        $this->assertSame([], Request::getTrustedHosts());
    }

    public function test_subdomains_are_not_implicitly_trusted(): void
    {
        $this->get('http://admin.caope.ayudafesi.com/login')
            ->assertBadRequest();
    }

    public function test_the_host_allowlist_can_be_overridden_in_configuration(): void
    {
        config()->set('security.trusted_hosts', ['custom.internal']);

        $this->get('http://custom.internal/up')
            ->assertOk();
        $this->get('http://caope.ayudafesi.com/up')
            ->assertBadRequest();
    }

    public function test_the_production_profile_does_not_trust_loopback_hosts(): void
    {
        config()->set(
            'security.trusted_hosts',
            config('security.trusted_host_profiles.production'),
        );

        $this->get('http://caope.ayudafesi.com/up')
            ->assertOk();
        $this->get('http://localhost/up')
            ->assertBadRequest();
        $this->get('http://127.0.0.1/up')
            ->assertBadRequest();
        $this->get('http://[::1]/up')
            ->assertBadRequest();
    }

    public function test_a_trusted_host_with_a_port_is_accepted(): void
    {
        $this->get('http://caope.ayudafesi.com:8443/login')
            ->assertOk();
    }

    public function test_previous_config_cache_keeps_public_hosts_and_headers_safe_during_upgrade(): void
    {
        config()->offsetUnset('security');

        $response = $this->get('https://caope.ayudafesi.com/up');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('https://attacker.example/up')->assertBadRequest();
    }

    public function test_security_headers_are_added_to_https_responses(): void
    {
        $response = $this->get('https://caope.ayudafesi.com/up');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $response->assertHeader(
            'Content-Security-Policy',
            config('security.headers.content_security_policy'),
        );
    }

    public function test_a_controller_can_set_a_stricter_content_security_policy(): void
    {
        Route::get('/strict-content', fn () => response('protected')->header(
            'Content-Security-Policy',
            "sandbox; default-src 'none'",
        ));

        $this->get('https://caope.ayudafesi.com/strict-content')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_is_not_added_to_plain_http_responses(): void
    {
        $response = $this->get('http://caope.ayudafesi.com/up');

        $response->assertOk();
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_security_headers_are_also_added_to_error_responses(): void
    {
        $response = $this->get('https://caope.ayudafesi.com/a-route-that-does-not-exist');

        $response->assertNotFound();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $response->assertHeader(
            'Content-Security-Policy',
            config('security.headers.content_security_policy'),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function trustedHosts(): array
    {
        return [
            'primary production domain' => ['caope.ayudafesi.com'],
            'UNAM production domain' => ['xocoyotzin.iztacala.unam.mx'],
            'local hostname' => ['localhost'],
            'IPv4 loopback' => ['127.0.0.1'],
            'IPv6 loopback' => ['[::1]'],
        ];
    }
}
