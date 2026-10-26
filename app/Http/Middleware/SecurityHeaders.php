<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return $this->apply($request, $next($request));
    }

    public function apply(Request $request, Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if (app()->environment('production') && config('operations.hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        // Browsers may apply form-action to the gateway redirect after an on-site POST.
        $formAction = $request->routeIs('appointments.show', 'payments.initiate') ? "'self' https://payment.zarinpal.com https://sandbox.zarinpal.com" : "'self'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; font-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action ".$formAction);
        return $response;
    }
}
