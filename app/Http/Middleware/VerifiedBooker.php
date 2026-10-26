<?php

namespace App\Http\Middleware;

use App\Modules\Booking\Application\ActorResolver;
use Closure;
use Illuminate\Http\Request;

final class VerifiedBooker
{
    public function handle(Request $request, Closure $next)
    {
        app(ActorResolver::class)->resolve($request);
        return $next($request);
    }
}
