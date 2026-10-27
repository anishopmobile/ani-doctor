<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class WebsiteMediaController extends Controller
{
    public function __invoke(Request $request, WebsiteMedia $media): Response
    {
        $canManage = $request->user()?->hasPermission('website.manage') === true;
        abort_unless($canManage || $media->ownerIsPublished(), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($media->path), 404);

        return response($disk->get($media->path), 200, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
