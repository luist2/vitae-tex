<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class HttpSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    public function test_html_responses_receive_private_security_headers_and_a_csp_nonce(): void
    {
        config(['security.content_security_policy.enabled' => true]);

        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Cache-Control', 'max-age=0')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("frame-src 'self' blob:", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $policy);

        preg_match("/'nonce-([^']+)'/", $policy, $matches);

        $this->assertArrayHasKey(1, $matches);
        $this->assertStringContainsString('nonce="'.$matches[1].'"', $response->getContent());
        $this->assertStringNotContainsString('fonts.bunny.net', $response->getContent());
    }

    public function test_csp_can_be_disabled_for_the_local_vite_development_server(): void
    {
        config(['security.content_security_policy.enabled' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_error_responses_receive_the_same_security_baseline(): void
    {
        config(['security.content_security_policy.enabled' => true]);

        $this->get('/route-that-does-not-exist')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_hsts_is_only_sent_for_secure_requests_when_enabled(): void
    {
        config(['security.strict_transport_security.max_age' => 31536000]);

        $this->get('https://localhost/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');

        $this->get('http://localhost/login')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_session_cookie_uses_the_production_security_contract(): void
    {
        config([
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $response = $this->get('https://localhost/login');
        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie): bool => $cookie->getName() === config('session.cookie'));

        $this->assertInstanceOf(Cookie::class, $sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());
    }

    public function test_a_trusted_proxy_can_report_the_original_https_scheme(): void
    {
        $this->app->instance('env', 'production');
        config([
            'trustedproxy.proxies' => ['10.0.0.1'],
            'security.trusted_hosts' => ['vitaetex.example'],
            'security.strict_transport_security.max_age' => 31536000,
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_HOST' => 'vitaetex.example',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('http://vitaetex.example/')
            ->assertRedirect('https://vitaetex.example/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_forwarded_headers_from_an_untrusted_address_are_ignored(): void
    {
        $this->app->instance('env', 'production');
        config([
            'trustedproxy.proxies' => ['10.0.0.1'],
            'security.trusted_hosts' => ['vitaetex.example'],
            'security.strict_transport_security.max_age' => 31536000,
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.20',
            'HTTP_HOST' => 'vitaetex.example',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('http://vitaetex.example/')
            ->assertRedirect('http://vitaetex.example/login')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_untrusted_hosts_are_rejected_outside_local_and_testing_environments(): void
    {
        $this->app->instance('env', 'production');
        config(['security.trusted_hosts' => ['vitaetex.example']]);

        $this->get('https://attacker.example/login')
            ->assertStatus(400);
    }
}
