<?php

// Reuse the guarded, disposable panel fixture; never seed a clinic database.
require __DIR__.'/panel-browser-seed.php';

$date = Carbon\CarbonImmutable::now('Asia/Tehran')->startOfDay();
$saturday = $date->subDays(($date->isoWeekday() + 1) % 7);
foreach (range(1, 7) as $weekday) {
    app(App\Modules\Scheduling\Application\ScheduleService::class)->saveWeekly(['weekday' => $weekday, 'is_closed' => $weekday === 5, 'confirm_affected' => true,
        'shifts' => $weekday === 5 ? [] : [['start_minute' => 1020, 'end_minute' => 1320, 'step_minutes' => 30, 'capacity' => 1]]], $admin->id);
}
$service = app(App\Modules\Scheduling\Application\WeeklyAnnouncementService::class);
foreach ([1 => ['working', 'روستای علی‌آباد'], 2 => ['official_holiday', null]] as $offset => [$status, $location]) {
    $day = $saturday->addDays($offset);
    $version = $service->range($day, $day)[0]['version'];
    $service->save($day, ['expected_version' => $version, 'mode' => 'manual', 'status' => $status, 'shifts' => [['start' => '16:00', 'end' => '20:00', 'label' => 'عصر']], 'location' => $location, 'outside_office' => $location !== null, 'notes' => null], $admin->id);
}
app(App\Modules\Website\Application\ThemeService::class)->save(['show_weekly_schedule' => true, 'weekly_schedule_notes' => 'پیش از مراجعه، برنامه حضور را بررسی کنید.']);
echo "WEEKLY_SYNTHETIC_FIXTURE_READY\n";
