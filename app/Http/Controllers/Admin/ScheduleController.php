<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Modules\Scheduling\Application\ScheduleService;
use App\Modules\Scheduling\Application\JalaliDate;
use App\Modules\Scheduling\Infrastructure\ScheduleException;
use App\Modules\Scheduling\Infrastructure\WeeklySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

final class ScheduleController extends Controller
{
    public function index(JalaliDate $jalali): View
    {
        return view('admin.schedule', ['weekly' => WeeklySchedule::with('shifts')->get()->keyBy('weekday'), 'exceptions' => ScheduleException::orderByDesc('start_date')->paginate(20), 'horizon' => DB::table('schedule_state')->where('id', 1)->value('horizon_days'), 'todayJalali' => $jalali->format(CarbonImmutable::now('Asia/Tehran')), 'jalali' => $jalali]);
    }

    public function weekly(ScheduleRequest $request, ScheduleService $schedule): RedirectResponse
    {
        $schedule->saveWeekly($request->scheduleData(), $request->user()->id);
        return back()->with('status', 'برنامه هفتگی ذخیره شد.');
    }

    public function exception(ScheduleRequest $request, ScheduleService $schedule): RedirectResponse
    {
        $schedule->saveException($request->scheduleData(), $request->user()->id);
        return back()->with('status', 'استثنا ثبت شد؛ نوبت موجود حذف نمی‌شود.');
    }

    public function destroy(Request $request, ScheduleException $exception, ScheduleService $schedule): RedirectResponse
    {
        $schedule->removeException($exception, $request->user()->id, $request->boolean('confirm_affected'));
        return back()->with('status', 'استثنا حذف شد؛ برنامه هفتگی دوباره اعمال می‌شود.');
    }

    public function horizon(\App\Http\Requests\HorizonRequest $request, ScheduleService $schedule): RedirectResponse
    {
        $data = $request->validated();
        $schedule->setHorizon((int) $data['horizon_days'], $request->user()->id);
        return back()->with('status', 'افق رزرو ذخیره شد.');
    }
}
