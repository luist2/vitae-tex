<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('security.content_security_policy.enabled')) {
            Vite::useCspNonce();
        }

        return $this->apply($request, $next($request));
    }

    public function apply(Request $request, Response $response): Response
    {
        $nonce = config('security.content_security_policy.enabled')
            ? Vite::cspNonce() ?? Vite::useCspNonce()
            : null;
        $headers = $response->headers;

        $headers->set('Cache-Control', 'private, no-store, max-age=0');
        $headers->set('Pragma', 'no-cache');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if (is_string($nonce) && $this->isHtml($response)) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        }

        $hstsMaxAge = config('security.strict_transport_security.max_age');

        if ($request->isSecure() && is_int($hstsMaxAge) && $hstsMaxAge > 0) {
            $headers->set('Strict-Transport-Security', "max-age={$hstsMaxAge}");
        }

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "connect-src 'self'",
            "font-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "frame-src 'self' blob:",
            "img-src 'self' data:",
            "manifest-src 'self'",
            "media-src 'none'",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$nonce}'",
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline'",
            "worker-src 'self' blob:",
        ]).';';
    }
}
