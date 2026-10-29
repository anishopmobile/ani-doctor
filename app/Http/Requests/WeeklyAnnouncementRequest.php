<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Scheduling\Application\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

final class WeeklyAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('schedules.manage');
    }

    protected function prepareForValidation(): void
    {
        // Normalize date/time digits only; preserve the editor's public text verbatim.
        $values = $this->all();
        foreach (['date_jalali', 'week'] as $key) {
            if (is_string($values[$key] ?? null)) {
                $values[$key] = MobileNumber::normalize($values[$key]);
            }
        }
        if (is_array($values['shifts'] ?? null)) {
            foreach ($values['shifts'] as &$row) {
                if (is_array($row)) {
                    foreach (['start', 'end'] as $key) {
                        if (is_string($row[$key] ?? null)) {
                            $row[$key] = MobileNumber::normalize($row[$key]);
                        }
                    }
                }
            }
            unset($row);
            $values['shifts'] = array_values(array_filter($values['shifts'], fn ($row) => !is_array($row) || !empty($row['start']) || !empty($row['end']) || !empty($row['label'])));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        if ($this->routeIs('weekly-announcements.settings')) {
            return ['show_weekly_schedule' => 'required|boolean', 'weekly_schedule_notes' => 'nullable|string|max:2000', 'expected_settings_version' => 'required|integer|min:0'];
        }
        if ($this->isMethod('GET')) {
            return ['week' => 'nullable|string|max:10'];
        }
        $working = $this->input('mode') === 'manual' && $this->input('status') === 'working';
        return [
            'date_jalali' => 'required|string|max:10', 'expected_version' => 'required|integer|min:0',
            'mode' => 'required|in:source,manual', 'status' => 'required_if:mode,manual|nullable|in:working,closed,official_holiday',
            'location' => 'nullable|string|max:200', 'outside_office' => 'required|boolean', 'notes' => 'nullable|string|max:2000',
            'shifts' => $working ? 'required|array|min:1|max:8' : 'exclude',
            'shifts.*' => $working ? 'required|array:start,end,label' : 'exclude',
            'shifts.*.start' => $working ? ['required', 'regex:/\A([01][0-9]|2[0-3]):[0-5][0-9]\z/'] : 'exclude',
            'shifts.*.end' => $working ? ['required', 'regex:/\A(([01][0-9]|2[0-3]):[0-5][0-9]|24:00)\z/'] : 'exclude',
            'shifts.*.label' => $working ? 'nullable|string|max:80' : 'exclude',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->routeIs('weekly-announcements.settings')) {
                return;
            }
            foreach ($this->isMethod('GET') ? ['week'] : ['date_jalali'] as $field) {
                if ($this->filled($field)) {
                    try {
                        app(JalaliDate::class)->parse($this->input($field));
                    } catch (\InvalidArgumentException) {
                        $validator->errors()->add($field, 'تاریخ شمسی معتبر با قالب 1405-08-01 وارد کنید.');
                    }
                }
            }
            if ($validator->errors()->isNotEmpty() || $this->isMethod('GET') || $this->input('mode') !== 'manual' || $this->input('status') !== 'working') {
                return;
            }
            $end = '';
            $rows = $this->validated('shifts', []);
            usort($rows, fn ($a, $b) => strcmp($a['start'], $b['start']));
            foreach ($rows as $row) {
                if ($row['end'] <= $row['start'] || $row['start'] < $end) {
                    $validator->errors()->add('shifts', 'بازه‌ها نباید هم‌پوشانی داشته باشند یا از نیمه‌شب عبور کنند؛ بازه را به دو روز تقسیم کنید.');
                }
                $end = $row['end'];
            }
        }];
    }

    public function messages(): array
    {
        return ['required' => 'تکمیل :attribute الزامی است.', 'required_if' => 'تکمیل :attribute الزامی است.', 'in' => ':attribute معتبر نیست.', 'regex' => 'ساعت را با قالب معتبر مانند 17:00 وارد کنید.', 'max.string' => ':attribute بیش از حد طولانی است.'];
    }

    public function attributes(): array
    {
        return ['date_jalali' => 'تاریخ', 'week' => 'تاریخ هفته', 'status' => 'وضعیت روز', 'location' => 'محل ویزیت', 'notes' => 'توضیحات روز', 'shifts' => 'بازه حضور', 'shifts.*.start' => 'ساعت شروع', 'shifts.*.end' => 'ساعت پایان', 'weekly_schedule_notes' => 'توضیحات عمومی'];
    }
}
