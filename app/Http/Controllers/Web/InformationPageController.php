<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Website\Application\InformationPageService;
use Illuminate\View\View;

final class InformationPageController extends Controller
{
    public function privacy(InformationPageService $pages): View
    {
        return view('website.privacy', [...$pages->published('privacy'), 'privacyGuide' => $pages->privacy(), 'canonicalUrl' => route('website.privacy')]);
    }

    public function policies(InformationPageService $pages): View
    {
        return view('website.policies', [...$pages->published('policies'), 'rules' => $pages->policies(), 'canonicalUrl' => route('website.policies')]);
    }
}
