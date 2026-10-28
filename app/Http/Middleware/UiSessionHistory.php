<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

final class UiSessionHistory extends StartSession
{
    protected function storeCurrentUrl(Request $request, $session)
    {
        // Asset requests are not navigation. In particular a private CSS preview
        // must not change another tab's redirect()->back() destination.
        if ($request->routeIs('website.theme.css', 'website.theme.preview-css', 'website.media.show')) {
            return;
        }
        parent::storeCurrentUrl($request, $session);
    }
}
