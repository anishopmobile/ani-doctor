<section class="patient-picker" data-patient-picker aria-labelledby="patient-picker-title">
<h3 id="patient-picker-title" class="h5">برای چه کسی نوبت می‌گیرید؟</h3>
<div data-patient-native><label class="form-label" for="booking-patient">انتخاب بیمار</label>
<select id="booking-patient" class="form-select" name="patient_id" data-patient-choice data-native-select>
<option value="">بیمار جدید</option>
@php($selectedPatient = old('patient_id', ($deskBooking ?? false) ? $patients->first()?->id : ''))
@foreach($patients as $patient)<option value="{{ $patient->id }}" @selected(is_scalar($selectedPatient) && (string)$selectedPatient === (string)$patient->id)>{{ $patient->name }} — تماس: {{ $patient->contact_mobile ?: 'ثبت نشده' }}@if($patient->file_number) — پرونده: {{ $patient->file_number }}@endif</option>@endforeach
</select></div>
<div class="patient-selection" data-patient-enhanced hidden><div><strong data-selected-patient>بیمار جدید</strong><p class="form-text mb-0" data-selected-patient-help>اطلاعات بیمار را در کادرهای زیر وارد کنید.</p></div><button class="btn btn-outline-primary" type="button" data-patient-open>انتخاب یا تغییر بیمار</button></div>
<dialog class="patient-dialog" data-patient-dialog aria-labelledby="patient-dialog-title">
<div class="ux-section-heading"><h2 id="patient-dialog-title" class="h4">انتخاب بیمار</h2><button class="btn btn-outline-secondary" type="button" data-patient-close aria-label="بستن انتخاب بیمار">بستن</button></div>
<p>نام و شماره تماس را بررسی کنید و سپس «انتخاب» را بزنید.</p>
@if(($deskBooking ?? false) && $actor->can('patients.manage'))
<label class="form-label" for="patient-find">جستجو با نام، شماره تماس کامل یا شماره پرونده</label><div class="ux-search-row"><input id="patient-find" class="form-control" data-patient-search maxlength="100" autocomplete="off"><button class="btn btn-primary" type="button" data-patient-find>جستجو</button></div>
<p data-patient-search-status role="status" aria-live="polite"></p><div class="d-grid gap-2" data-patient-results></div>
@endif
<label class="form-label" for="patient-local-search">جستجو در بیماران این فهرست</label><input id="patient-local-search" class="form-control mb-3" type="search" data-patient-filter autocomplete="off">
<div class="patient-options" data-patient-options></div><p data-patient-empty role="status" hidden>بیماری با این عبارت در فهرست نیست.</p>
<button class="btn btn-outline-primary mt-3 w-100" type="button" data-patient-new>بیمار جدید؛ واردکردن مشخصات</button>
</dialog>
</section>
