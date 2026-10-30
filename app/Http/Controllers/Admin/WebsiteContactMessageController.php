<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Website\Application\ContactMessageService;
use App\Modules\Website\Infrastructure\WebsiteContactMessage;

final class WebsiteContactMessageController extends Controller
{
    public function index(): \Illuminate\Http\Response
    {
        return response()->view('admin.website-contact-messages', ['messages' => WebsiteContactMessage::latest('id')->paginate(20)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(WebsiteContactMessage $message, ContactMessageService $messages): \Illuminate\Http\RedirectResponse
    {
        $messages->markRead($message);
        return back()->with('status', 'پیام خوانده‌شده علامت‌گذاری شد.');
    }

    public function destroy(WebsiteContactMessage $message): \Illuminate\Http\RedirectResponse
    {
        $message->delete();
        return back()->with('status', 'پیام حذف شد.');
    }
}
