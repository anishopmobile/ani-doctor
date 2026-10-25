<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Scheduling\Application\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

final class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('schedules.manage');
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        array_walk_recursive($input, function (&$value): void {
            if (is_string($value)) {
                $value = MobileNumber::normalize($value);
            }
        });
        if (isset($input['shifts']) && is_array($input['shifts'])) {
            $input['shifts'] = array_values(array_filter($input['shifts'], fn ($row) => !is_array($row) || !empty($row['start']) || !empty($row['end'])));
        }
        $this->merge($input);
    }

    public function rules(): array
    {
        $weekly = $this->routeIs('schedule.weekly');
        $closed = $weekly ? $this->boolean('is_closed') : $this->input('kind') === 'closed';
        return [
            'weekday' => $weekly ? 'required|integer|between:1,7' : 'prohibited',
            'is_closed' => $weekly ? 'required|boolean' : 'prohibited',
            'kind' => $weekly ? 'prohibited' : 'required|in:closed,override',
            'start_jalali' => $weekly ? 'prohibited' : 'required|string|max:10',
            'end_jalali' => $weekly ? 'prohibited' : 'required|string|max:10',
            'reason' => $weekly ? 'nullable|string|max:500' : 'required|string|max:500',
            'confirm_affected' => 'sometimes|boolean',
            'shifts' => [$closed ? 'present' : 'required', 'array', 'max:8'],
            'shifts.*.start' => ['required', 'regex:/\A([01][0-9]|2[0-3]):[0-5][0-9]\z/'],
            'shifts.*.end' => ['required', 'regex:/\A(([01][0-9]|2[0-3]):[0-5][0-9]|24:00)\z/'],
            'shifts.*.step_minutes' => 'required|integer|between:5,120',
            'shifts.*.capacity' => 'required|integer|between:1,20',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $shifts = $this->convertedShifts();
            usort($shifts, fn ($a, $b) => $a['start_minute'] <=> $b['start_minute']);
            $end = -1;
            foreach ($shifts as $shift) {
                if ($shift['end_minute'] <= $shift['start_minute'] || $shift['start_minute'] < $end) {
                    $validator->errors()->add('shifts', 'شیفت‌ها نباید هم‌پوشانی داشته باشند یا از نیمه‌شب عبور کنند.');
                }
                $end = $shift['end_minute'];
            }
            if (!$this->routeIs('schedule.weekly')) {
                try {
                    $from = app(JalaliDate::class)->parse($this->input('start_jalali'));
                    $to = app(JalaliDate::class)->parse($this->input('end_jalali'));
                    if ($to < $from || $to > $from->addDays(365)) {
                        $validator->errors()->add('end_jalali', 'بازه تاریخ معتبر و حداکثر یک سال باشد.');
                    }
                } catch (\InvalidArgumentException) {
                    $validator->errors()->add('start_jalali', 'تاریخ شمسی معتبر با قالب 1405-08-01 وارد کنید.');
                }
            }
        }];
    }

    public function scheduleData(): array
    {
        $data = $this->validated();
        $data['shifts'] = $this->convertedShifts();
        if (!$this->routeIs('schedule.weekly')) {
            $data['start_date'] = app(JalaliDate::class)->parse($data['start_jalali'])->toDateString();
            $data['end_date'] = app(JalaliDate::class)->parse($data['end_jalali'])->toDateString();
            if ($data['kind'] === 'closed') {
                $data['shifts'] = [];
            }
        }
        return $data;
    }

    private function convertedShifts(): array
    {
        return array_map(function ($row): array {
            $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
            return ['start_minute' => $minutes($row['start']), 'end_minute' => $minutes($row['end']), 'step_minutes' => (int) $row['step_minutes'], 'capacity' => (int) $row['capacity']];
        }, $this->input('shifts', []));
    }
}
