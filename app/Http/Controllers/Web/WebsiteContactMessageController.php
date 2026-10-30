<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteContactMessageRequest;
use App\Modules\Website\Application\ContactMessageService;

final class WebsiteContactMessageController extends Controller
{
    public function __invoke(WebsiteContactMessageRequest $request, ContactMessageService $messages): \Illuminate\Http\RedirectResponse
    {
        $messages->receive($request->validated());
        return redirect()->to(route('website.home').'#contact')->with('contact_status', 'پیام شما ثبت شد و در اختیار مدیر مطب قرار گرفت.');
    }
}
