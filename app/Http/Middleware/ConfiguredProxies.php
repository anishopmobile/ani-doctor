<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConfiguredProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT;
        $request::setTrustedProxies([], $headers);
        $proxies = config('operations.trusted_proxies', []);
        if (!\App\Modules\Operations\Domain\ProxyAddresses::valid($proxies)) {
            return response('تنظیم پراکسی معتبر نیست؛ با مسئول سامانه تماس بگیرید.', 503);
        }
        // Never trust forwarded Host, prefixes or an unrestricted wildcard.
        $request::setTrustedProxies($proxies, $headers);
        return $next($request);
    }
}
