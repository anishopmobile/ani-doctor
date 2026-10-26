@props(['shifts' => []])
<p class="form-text">برای وقفه، دو شیفت جدا ثبت کنید. شروع و پایان هر دو لازم‌اند. شیفت عبوری از نیمه‌شب را به دو روز تقسیم کنید.</p>
@for($i=0; $i < max(3, count($shifts)); $i++)
    @php($shift = $shifts[$i] ?? [])
    <fieldset class="shift-row border rounded p-2 mb-2">
        <legend class="h6">شیفت {{ $i+1 }}</legend>
        <label>شروع (ساعت:دقیقه)<input class="form-control" name="shifts[{{ $i }}][start]" value="{{ isset($shift['start_minute']) ? sprintf('%02d:%02d', intdiv($shift['start_minute'],60),$shift['start_minute']%60) : ($shift['start'] ?? '') }}" type="time" step="60" dir="ltr"></label>
        <label>پایان<input class="form-control" name="shifts[{{ $i }}][end]" value="{{ isset($shift['end_minute']) ? sprintf('%02d:%02d', intdiv($shift['end_minute'],60),$shift['end_minute']%60) : ($shift['end'] ?? '') }}" type="time" step="60" dir="ltr"></label>
        <label>گام (دقیقه)<input class="form-control" type="number" name="shifts[{{ $i }}][step_minutes]" value="{{ $shift['step_minutes'] ?? 30 }}" min="5" max="120"></label>
        <label>ظرفیت<input class="form-control" type="number" name="shifts[{{ $i }}][capacity]" value="{{ $shift['capacity'] ?? 1 }}" min="1" max="20"></label>
    </fieldset>
@endfor
