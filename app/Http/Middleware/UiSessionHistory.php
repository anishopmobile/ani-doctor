<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

final class UiSessionHistory extends StartSession
{
    protected function storeCurrentUrl(Request $request, $session)
    {
        // Assets and attachment downloads are not page navigation. They must not
        // replace the form's back destination with a file response after validation.
        if ($request->routeIs('website.theme.css', 'website.theme.preview-css', 'website.media.show', 'emr.documents.download')) {
            return;
        }
        parent::storeCurrentUrl($request, $session);
    }
}
