<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Application\WeeklyAnnouncementService;
use App\Modules\Website\Application\ThemeService;
use Illuminate\View\View;

final class WeeklyAnnouncementController extends Controller
{
    public function __invoke(WeeklyAnnouncementService $service, ThemeService $theme): View
    {
        $settings = $theme->settings();
        abort_unless($settings['show_weekly_schedule'] === true, 404);
        return view('website.weekly-schedule', ['week' => $service->week(), 'notes' => $settings['weekly_schedule_notes']]);
    }
}
