<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Scheduling\Application\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission($this->input('kind', 'appointments') === 'finance' ? 'reports.finance' : 'reports.appointments') ?? false;
    }
    protected function prepareForValidation(): void
    {
        $jalali = app(JalaliDate::class);
        $this->merge(['kind' => $this->input('kind', 'appointments'), 'from' => is_string($this->input('from')) ? MobileNumber::normalize($this->input('from')) : $this->input('from', $jalali->format(now('Asia/Tehran')->subDays(30))), 'to' => is_string($this->input('to')) ? MobileNumber::normalize($this->input('to')) : $this->input('to', $jalali->format(now('Asia/Tehran'))), 'sort' => $this->input('sort', 'date'), 'direction' => $this->input('direction', 'asc')]);
    }
    public function rules(): array
    {
        return ['kind' => 'required|in:appointments,finance', 'from' => 'required|string|max:10', 'to' => 'required|string|max:10', 'sort' => 'required|in:date,amount', 'direction' => 'required|in:asc,desc', 'service_id' => 'nullable|integer|exists:services,id', 'operator_id' => 'nullable|integer|exists:users,id', 'status' => 'nullable|in:pending_payment,confirmed,expired,cancelled,completed,no_show', 'channel' => 'nullable|in:guest,user,secretary,phone,urgent', 'payment_status' => 'nullable|in:unpaid,paid,credited', 'attendance_status' => 'nullable|in:scheduled,arrived,completed,no_show', 'format' => 'nullable|in:pdf,xlsx'];
    }
    public function filters(): array
    {
        $data = $this->validated();
        try {
            $start = app(JalaliDate::class)->parse($data['from']);
            $end = app(JalaliDate::class)->parse($data['to'])->addDay();
            if ($end->lessThanOrEqualTo($start) || $start->diffInDays($end) > 366) {
                throw new \InvalidArgumentException();
            }
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['from' => 'بازه شمسی باید معتبر و حداکثر ۳۶۶ روز باشد.']);
        }
        if ($data['kind'] === 'finance' && array_filter(array_intersect_key($data, array_flip(['service_id', 'operator_id', 'status', 'channel', 'payment_status', 'attendance_status'])))) {
            throw ValidationException::withMessages(['kind' => 'گزارش مالی کل مطب فقط بازه زمانی دارد؛ فیلتر نوبت را در گزارش نوبت‌ها اعمال کنید.']);
        }
        return [...$data, 'start' => $start->utc()->toDateTimeString(), 'end' => $end->utc()->toDateTimeString(), 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()];
    }
    public function attributes(): array
    {
        return ['kind' => 'گزارش', 'from' => 'از تاریخ', 'to' => 'تا تاریخ', 'sort' => 'مرتب‌سازی', 'direction' => 'ترتیب', 'operator_id' => 'ثبت‌کننده', 'status' => 'وضعیت نوبت', 'payment_status' => 'وضعیت مالی', 'attendance_status' => 'حضور', 'channel' => 'روش ثبت'];
    }
}
