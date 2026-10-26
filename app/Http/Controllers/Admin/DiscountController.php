<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscountRequest;
use App\Modules\Billing\Application\DiscountService;
use Illuminate\Support\Facades\DB;

final class DiscountController extends Controller
{
    public function index()
    {
        return view('discounts.index', ['offers' => DB::table('discounts')->orderByDesc('id')->paginate(20), 'services' => \App\Modules\Catalog\Infrastructure\Service::orderBy('name')->get(), 'jalali' => app(\App\Modules\Scheduling\Application\JalaliDate::class)]);
    }
    public function save(DiscountRequest $request, DiscountService $service, ?int $discount = null)
    {
        if ($discount) {
            $data = $request->validated();
            $service->toggle($request->user(), $discount, (int) $data['expected_version'], (bool) $data['is_active'], $data['reason']);
        } else {
            $service->create($request->user(), $request->offer());
        }
        return redirect()->route('discounts.index')->with('status', 'تخفیف ثبت شد؛ قیمت نوبت‌های قبلی تغییر نکرد.');
    }
}
