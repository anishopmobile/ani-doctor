<?php

require __DIR__.'/panel-browser-seed.php';
if (!config('bale.enabled') || config('bale.bot_token') !== 'synthetic-browser-token' || config('bale.safir_key') !== '') {
    throw new RuntimeException('SYNTHETIC_BALE_ONLY');
}
$owner = App\Modules\Identity\Infrastructure\User::where('mobile', '09000000552')->sole();
$patient = App\Modules\Patients\Infrastructure\Patient::firstOrCreate(['name' => 'بیمار ساختگی بله']);
Illuminate\Support\Facades\DB::table('patient_user_links')->insertOrIgnore(['patient_id' => $patient->id, 'user_id' => $owner->id, 'created_at' => now()]);
Illuminate\Support\Facades\DB::table('bale_links')->updateOrInsert(['bale_id' => '123'], ['user_id' => $owner->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
$actor = new App\Modules\Identity\Domain\BookingActor($admin->id, $admin->mobile, ['payments.settings'], (string) config('app.key'));
$settings = Illuminate\Support\Facades\DB::table('card_transfer_settings')->where('id', 1)->first();
app(App\Modules\Payments\Application\CardTransferService::class)->configure($actor, ['enabled' => true, 'card_number' => '0000000000000000', 'holder' => 'کارت ساختگی آزمون', 'expected_version' => $settings->version ?? 0]);
foreach (range(1, 7) as $weekday) {
    app(App\Modules\Scheduling\Application\ScheduleService::class)->saveWeekly(['weekday' => $weekday, 'is_closed' => false, 'confirm_affected' => true, 'shifts' => [['start_minute' => 480, 'end_minute' => 1320, 'step_minutes' => 30, 'capacity' => 1]]], $admin->id);
}
echo "Synthetic Bale browser fixture ready\n";
Illuminate\Support\Facades\DB::table('booking_settings')->where('id', 1)->update(['max_active_patient' => 100, 'max_active_booker' => 100]);
