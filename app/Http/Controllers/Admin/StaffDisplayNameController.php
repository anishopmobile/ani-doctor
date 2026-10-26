<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffDisplayNameRequest;
use App\Modules\Reporting\Application\StaffNameService;

final class StaffDisplayNameController extends Controller
{
    public function __invoke(StaffDisplayNameRequest $request, StaffNameService $names)
    {
        $data = $request->validated();
        $names->save($request->user(), (int) $data['user_id'], $data['display_name'], $data['reason']);
        return back()->with('status', 'نام نمایشی ثبت شد؛ شناسه ثبت‌کننده رسیدها تغییر نکرد.');
    }
}
