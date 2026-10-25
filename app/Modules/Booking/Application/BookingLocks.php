<?php

namespace App\Modules\Booking\Application;

use Illuminate\Support\Facades\DB;

final class BookingLocks
{
    public function configuration(): object
    {
        // Shared lock permits unrelated days to book concurrently, but excludes schedule edits.
        DB::table('schedule_state')->where('id', 1)->sharedLock()->first();
        return DB::table('booking_settings')->where('id', 1)->sharedLock()->first();
    }
    public function actors(array $keys): void
    {
        sort($keys, SORT_STRING);
        foreach (array_unique($keys) as $key) {
            DB::table('booking_actor_locks')->insertOrIgnore(['key' => $key]);
            DB::table('booking_actor_locks')->where('key', $key)->lockForUpdate()->first();
        }
    }
    public function days(array $dates): void
    {
        sort($dates, SORT_STRING);
        foreach (array_unique($dates) as $date) {
            DB::table('booking_day_locks')->insertOrIgnore(['date' => $date]);
            DB::table('booking_day_locks')->where('date', $date)->lockForUpdate()->first();
        }
    }
}
