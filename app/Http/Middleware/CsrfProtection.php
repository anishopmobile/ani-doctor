<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

final class CsrfProtection extends PreventRequestForgery
{
    // Forms and Fetch send the session token explicitly; no readable XSRF cookie is needed.
    // Inherit token/origin verification and the narrow gateway callback exception unchanged.
    protected $addHttpCookie = false;
}
