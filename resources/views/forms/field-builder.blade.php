<fieldset class="border rounded p-3 mb-3" data-field-row>
<legend class="h6">فیلد فرم</legend><div class="row g-3">
<div class="col-md-4"><label class="form-label">عنوان فیلد<input class="form-control" name="schema[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" maxlength="150" required></label></div>
<div class="col-md-4"><label class="form-label">نوع پاسخ<select class="form-select" name="schema[{{ $index }}][type]">@foreach(['text'=>'متن','number'=>'عدد','date'=>'تاریخ میلادی','select'=>'انتخاب','multiselect'=>'چندانتخابی','checkbox'=>'بله / خیر'] as $type=>$label)<option value="{{ $type }}" @selected(($field['type'] ?? '') === $type)>{{ $label }}</option>@endforeach</select></label></div>
<div class="col-md-4"><label class="form-label">پاسخ اجباری<select class="form-select" name="schema[{{ $index }}][required]"><option value="0">خیر</option><option value="1" @selected($field['required'] ?? false)>بله</option></select></label></div>
<input type="hidden" name="schema[{{ $index }}][key]" value="{{ $field['key'] ?? 'field_'.$index }}" data-field-key>
<div class="col-md-6"><label class="form-label">گزینه‌ها؛ هر مورد یک خط (برای انتخاب)<textarea class="form-control" name="schema[{{ $index }}][options_text]" rows="3">{{ implode("\n", $field['options'] ?? []) }}</textarea></label></div>
<div class="col-md-3"><label class="form-label">حداقل عدد<input class="form-control" type="number" name="schema[{{ $index }}][min]" value="{{ $field['min'] ?? '' }}"></label></div>
<div class="col-md-3"><label class="form-label">حداکثر عدد<input class="form-control" type="number" name="schema[{{ $index }}][max]" value="{{ $field['max'] ?? '' }}"></label></div>
<div class="col-md-6"><label class="form-label">نمایش وابسته به پاسخ فیلد قبلی<select class="form-select" name="schema[{{ $index }}][condition][field]" data-condition-source data-selected="{{ $field['condition']['field'] ?? '' }}"><option value="">همیشه نمایش داده شود</option></select></label></div>
<div class="col-md-6"><label class="form-label">برابر با (بله: ۱، خیر: ۰)<input class="form-control" name="schema[{{ $index }}][condition][equals]" value="{{ $field['condition']['equals'] ?? '' }}"></label></div>
</div><button class="btn btn-outline-danger mt-3" type="button" data-remove-field>حذف فیلد از نسخه جدید</button>
</fieldset>
