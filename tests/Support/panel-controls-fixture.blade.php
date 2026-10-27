@extends('layouts.app')
@section('content')
<h1>فرم ساختگی آزمون کنترل‌ها</h1>
<form id="test-form" method="post" action="/__ui-submit">
@csrf
<div class="field-grid">
<label>گزارش<select id="kind" class="form-select" name="kind"><option value="appointments">نوبت‌ها</option><option value="finance">مالی کل مطب</option><option value="payload">{{ '<img src=x onerror="window.__unsafe=1">' }}</option><option value="disabled" disabled>گزینه غیرفعال</option></select></label>
<label>انتخاب الزامی<select id="required-choice" class="form-select" name="required_choice" required><option value="">انتخاب کنید</option><option value="yes">گزینه معتبر</option></select></label>
<label>خدمات چندانتخابی<select id="multiple" class="form-select" name="services[]" multiple><option value="a" selected>خدمت اول</option><option value="b">خدمت دوم</option><option value="c" disabled>خدمت غیرفعال</option></select></label>
<label>تاریخ شمسی<input id="date" name="date" class="form-control" data-persian-date value="1405-08-03" maxlength="10" required></label>
</div>
<fieldset id="disabled-group" disabled><label>کنترل وابسته<select id="conditional" class="form-select" name="conditional"><option value="a">اول</option><option value="b">دوم</option></select></label><label>تاریخ وابسته<input id="conditional-date" class="form-control" data-persian-date value="1405-08-01"></label></fieldset>
<button class="btn btn-primary mt-3" type="submit">ارسال فرم ساختگی</button><button class="btn btn-outline-secondary" type="reset">بازنشانی</button>
</form>
@endsection
