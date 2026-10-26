<?php

// Explicitly isolated, synthetic UI data. Never runs against the installation database.
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('UI_SYNTHETIC_FIXTURE') !== '1' || config('database.default') !== 'sqlite'
    || basename(config('database.connections.sqlite.database')) !== 'ui-preview.sqlite'
    || config('payments.zarinpal.enabled') || config('notifications.enabled')) {
    throw new RuntimeException('Isolated synthetic UI database required.');
}
foreach (['admin' => '09120000001', 'secretary' => '09120000002', 'patient' => '09123456789'] as $role => $mobile) {
    $user = App\Modules\Identity\Infrastructure\User::firstOrCreate(['mobile' => $mobile]);
    $user->forceFill(['mobile_verified_at' => now()])->save();
    $user->roles()->sync([App\Modules\Identity\Infrastructure\Role::where('name', $role)->sole()->id]);
}
$admin = App\Modules\Identity\Infrastructure\User::where('mobile', '09120000001')->sole();
$patient = App\Modules\Identity\Infrastructure\User::where('mobile', '09123456789')->sole();
foreach (['ویزیت اولیه (آزمایشی)', 'ویزیت مجدد (آزمایشی)', 'مشاوره مطب (آزمایشی)'] as $index => $name) {
    App\Modules\Catalog\Infrastructure\Service::create(['name' => $name, 'price_irr' => 1000000 + 200000 * $index, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
}
foreach (range(1, 7) as $day) {
    app(App\Modules\Scheduling\Application\ScheduleService::class)->saveWeekly(['weekday' => $day, 'is_closed' => false, 'shifts' => [['start_minute' => 480, 'end_minute' => 1320, 'step_minutes' => 30, 'capacity' => 1]]], $admin->id);
}
$service = App\Modules\Catalog\Infrastructure\Service::firstOrFail();
$actor = new App\Modules\Identity\Domain\BookingActor($patient->id, $patient->mobile, [], (string) config('app.key'));
$appointment = app(App\Modules\Booking\Application\BookingService::class)->create($actor, [
    'service_id' => $service->id, 'expected_price_irr' => $service->price_irr,
    'start_at' => Carbon\CarbonImmutable::now('Asia/Tehran')->addDays(3)->setTime(17, 0)->utc()->toIso8601String(),
    'patient_name' => 'بیمار ساختگی آزمون رابط', 'idempotency_key' => (string) Illuminate\Support\Str::uuid(),
]);
$form = app(App\Modules\DynamicForms\Application\PrivateFormService::class)->publish($admin, null, [
    'title' => 'فرم ساختگی آزمون رابط', 'service_id' => $service->id, 'is_active' => true, 'expected_version' => 0, 'reason' => 'آزمون خودکار',
    'schema' => [
        ['key' => 'choice', 'label' => 'نمایش تاریخ وابسته', 'type' => 'select', 'required' => true, 'options' => ['نمایش', 'پنهان']],
        ['key' => 'visit_date', 'label' => 'تاریخ انتخابی', 'type' => 'date', 'required' => true, 'condition' => ['field' => 'choice', 'equals' => 'نمایش']],
        ['key' => 'tags', 'label' => 'انتخاب چند مورد', 'type' => 'multiselect', 'required' => false, 'options' => ['گزینه اول', 'گزینه دوم', 'گزینه سوم']],
    ],
]);
$deskDay = Carbon\CarbonImmutable::now('Asia/Tehran')->addDay();
foreach (['بیمار آزمایشی اول','بیمار آزمایشی دوم','بیمار آزمایشی سوم'] as $index => $name) {
    $guest = new App\Modules\Identity\Domain\BookingActor(null, '0900000000'.($index + 1), [], (string) config('app.key'));
    app(App\Modules\Booking\Application\BookingService::class)->create($guest, [
        'service_id' => $service->id, 'expected_price_irr' => $service->price_irr,
        'start_at' => $deskDay->setTime(9 + $index, 0)->utc()->toIso8601String(),
        'patient_name' => $name, 'contact_mobile' => '0900000000'.($index + 1),
        'idempotency_key' => (string) Illuminate\Support\Str::uuid(),
    ]);
}
echo json_encode(['synthetic' => true, 'appointment' => $appointment->reference, 'form' => $form, 'desk_day' => app(App\Modules\Scheduling\Application\JalaliDate::class)->format($deskDay)], JSON_THROW_ON_ERROR)."\n";
