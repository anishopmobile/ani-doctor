<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeeklyAnnouncementRequest;
use App\Modules\Scheduling\Application\JalaliDate;
use App\Modules\Scheduling\Application\WeeklyAnnouncementService;
use App\Modules\Website\Application\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class WeeklyAnnouncementController extends Controller
{
    public function index(WeeklyAnnouncementRequest $request, WeeklyAnnouncementService $service, ThemeService $theme): View
    {
        return view('admin.weekly-announcements', ['week' => $service->week($request->validated('week')), 'settings' => $theme->settings()]);
    }

    public function save(WeeklyAnnouncementRequest $request, WeeklyAnnouncementService $service, JalaliDate $jalali): RedirectResponse
    {
        $data = $request->validated();
        $service->save($jalali->parse($data['date_jalali']), $data, $request->user()->id);
        return to_route('weekly-announcements.index', ['week' => $data['date_jalali']])->with('status', 'برنامه اطلاع‌رسانی ذخیره شد.');
    }

    public function settings(WeeklyAnnouncementRequest $request, ThemeService $theme): RedirectResponse
    {
        $data = $request->validated();
        $data['weekly_schedule_notes'] ??= '';
        $theme->save($data);
        return back()->with('status', 'تنظیمات برنامه هفتگی ذخیره شد.');
    }
}
