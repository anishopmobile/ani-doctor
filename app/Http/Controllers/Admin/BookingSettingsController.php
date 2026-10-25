<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingSettingsRequest;
use App\Modules\SettingsAudit\Application\AuditService;
use Illuminate\Support\Facades\DB;

final class BookingSettingsController extends Controller
{
    public function index()
    {
        return view('booking.settings', ['settings' => DB::table('booking_settings')->where('id', 1)->first()]);
    }
    public function update(BookingSettingsRequest $request, AuditService $audit)
    {
        DB::transaction(function () use ($request, $audit): void {
            $before = DB::table('booking_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('booking_settings')->where('id', 1)->update($request->validated());
            $audit->record($request->user()->id, 'booking.settings', (array)$before, $request->validated());
        });
        return back()->with('status', 'سیاست رزرو ذخیره شد.');
    }
}
