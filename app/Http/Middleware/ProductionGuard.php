<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductionGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->environment('production')) {
            return $next($request);
        }

        $origin = parse_url((string) config('app.url'));
        if (config('app.debug') || ($origin['scheme'] ?? '') !== 'https'
            || !isset($origin['host']) || !config('session.secure')
            || !config('session.http_only') || !config('session.encrypt')
            || !in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            return $this->unavailable();
        }
        if (strcasecmp($request->getHost(), $origin['host']) !== 0) {
            return response('میزبان درخواست معتبر نیست.', 421);
        }
        // Do not reflect Host, forward credentials over HTTP, or trust arbitrary proxy headers.
        if (!$request->isSecure()) {
            return response('این سامانه فقط با اتصال HTTPS در دسترس است.', 426);
        }

        return $next($request);
    }

    private function unavailable(): Response
    {
        return response('پیکربندی امن سامانه کامل نیست؛ با مسئول سامانه تماس بگیرید.', 503);
    }
}
