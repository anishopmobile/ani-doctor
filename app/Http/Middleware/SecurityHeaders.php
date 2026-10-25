<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        // Browsers may apply form-action to the gateway redirect after an on-site POST.
        $formAction = $request->routeIs('appointments.show', 'payments.initiate') ? "'self' https://payment.zarinpal.com https://sandbox.zarinpal.com" : "'self'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; font-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action ".$formAction);
        return $response;
    }
}
