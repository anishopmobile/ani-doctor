<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmsTemplateRequest;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Notifications\Application\SmsSettingsService;
use Illuminate\Support\Facades\DB;

final class NotificationController extends Controller
{
    public function index()
    {
        return view('notifications.index', ['templates' => DB::table('sms_templates')->get()->keyBy('event'), 'events' => \App\Modules\Notifications\Application\NotificationOutbox::EVENTS, 'secretaries' => User::where('is_active', true)->whereNotNull('mobile_verified_at')->whereHas('roles', fn ($q) => $q->where('name', 'secretary'))->get(), 'messages' => DB::table('message_outbox')->select('id', 'event', 'state', 'attempts', 'delivery_code', 'created_at', 'updated_at')->orderByDesc('id')->paginate(20)]);
    }
    public function save(SmsTemplateRequest $request, SmsSettingsService $settings)
    {
        $settings->save($request->user(), $request->validated());
        return back()->with('status', 'تنظیم قالب ثبت شد؛ پیامک تازه‌ای با این اقدام ارسال نشد.');
    }
}
