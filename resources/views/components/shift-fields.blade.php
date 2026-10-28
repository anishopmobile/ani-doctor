@props(['shifts' => [], 'context' => null])
@if($context && old('_ux_context') === $context && is_array(old('shifts')))
@php($shifts = array_map(fn ($row) => array_intersect_key($row, array_flip(['start','end','step_minutes','capacity'])), array_slice(array_values(array_filter(old('shifts'), 'is_array')), 0, 8)))
@endif
<p class="form-text">برای وقفه، دو شیفت جدا ثبت کنید. شروع و پایان هر دو لازم‌اند. شیفت عبوری از نیمه‌شب را به دو روز تقسیم کنید.</p>
@for($i=0; $i < max(3, count($shifts)); $i++)
    @php($shift = $shifts[$i] ?? [])
    @php($shiftValue = fn ($key, $default = '') => is_scalar($shift[$key] ?? null) ? $shift[$key] : $default)
    <fieldset class="shift-row border rounded p-2 mb-2">
        <legend class="h6">شیفت {{ $i+1 }}</legend>
        <label>شروع (ساعت:دقیقه)<input class="form-control" name="shifts[{{ $i }}][start]" value="{{ isset($shift['start_minute']) ? sprintf('%02d:%02d', intdiv($shift['start_minute'],60),$shift['start_minute']%60) : $shiftValue('start') }}" placeholder="17:00" dir="ltr" inputmode="numeric"></label>
        <label>پایان<input class="form-control" name="shifts[{{ $i }}][end]" value="{{ isset($shift['end_minute']) ? sprintf('%02d:%02d', intdiv($shift['end_minute'],60),$shift['end_minute']%60) : $shiftValue('end') }}" placeholder="22:00" dir="ltr" inputmode="numeric"></label>
        <label>گام (دقیقه)<input class="form-control" type="number" name="shifts[{{ $i }}][step_minutes]" value="{{ $shiftValue('step_minutes', 30) }}" min="5" max="120"></label>
        <label>ظرفیت<input class="form-control" type="number" name="shifts[{{ $i }}][capacity]" value="{{ $shiftValue('capacity', 1) }}" min="1" max="20"></label>
    </fieldset>
@endfor
