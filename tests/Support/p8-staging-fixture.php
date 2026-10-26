<?php

// Synthetic fixture is excluded from the installation ZIP and requires an isolated named DB.
$root = getenv('P8_APP_ROOT') ?: '/var/www/app';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('P8_SYNTHETIC_FIXTURE') !== '1' || config('database.connections.mysql.database') !== 'ani_doctor_p8_smoke'
    || config('payments.zarinpal.enabled', false) || config('notifications.enabled', false)) {
    throw new RuntimeException('Isolated synthetic staging required.');
}
$service = App\Modules\Catalog\Infrastructure\Service::create(['name' => 'خدمت ساختگی آزمون بار', 'price_irr' => 1000000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
$staff = App\Modules\Identity\Infrastructure\User::create(['mobile' => '09000000008']);
$staff->forceFill(['mobile_verified_at' => now()])->save();
$staff->roles()->attach(App\Modules\Identity\Infrastructure\Role::where('name', 'admin')->sole()->id);
foreach (range(1, 7) as $day) {
    app(App\Modules\Scheduling\Application\ScheduleService::class)->saveWeekly(['weekday' => $day, 'is_closed' => false, 'shifts' => [['start_minute' => 480, 'end_minute' => 1320, 'step_minutes' => 30, 'capacity' => 1]]], $staff->id);
}
$patient = App\Modules\Patients\Infrastructure\Patient::create(['name' => 'بیمار ساختگی آزمون بار']);
foreach (range(0, 1049) as $index) {
    $future = $index >= 1000;
    $day = $future ? intdiv($index - 1000, 10) + 1 : -intdiv($index, 20) - 1;
    $slot = $future ? ($index - 1000) % 10 : $index % 20;
    $start = Carbon\CarbonImmutable::now('Asia/Tehran')->addDays($day)->startOfDay()->addMinutes(480 + 30 * $slot)->utc();
    $owner = hash_hmac('sha256', 'synthetic:'.$index, config('app.key'));
    $rowPatient = $future ? App\Modules\Patients\Infrastructure\Patient::create(['name' => 'بیمار ساختگی مستقل '.$index]) : $patient;
    App\Modules\Booking\Infrastructure\Appointment::create(['reference' => (string) Illuminate\Support\Str::uuid(), 'patient_id' => $rowPatient->id, 'booker_mobile_hash' => $owner, 'service_id' => $service->id, 'service_title' => $service->name, 'price_irr' => 0, 'base_price_irr' => 0, 'discount_irr' => 0, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity_limit' => 1, 'local_date' => $start->setTimezone('Asia/Tehran')->toDateString(), 'start_at' => $start, 'end_at' => $start->addMinutes(30), 'occupied_until' => $start->addMinutes(30), 'status' => $future ? 'confirmed' : 'completed', 'payment_status' => 'paid', 'balance_due_irr' => 0, 'channel' => 'user', 'version' => 1]);
}
$tomorrow = Carbon\CarbonImmutable::now('Asia/Tehran')->addDay();
echo json_encode(['synthetic' => true, 'appointments' => 1050, 'active' => 50, 'service' => $service->id, 'date' => $tomorrow->toDateString(), 'month' => substr(app(App\Modules\Scheduling\Application\JalaliDate::class)->format($tomorrow), 0, 7)]).PHP_EOL;
