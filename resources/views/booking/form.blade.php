@if(!$actor)
<p class="alert alert-info"><a class="btn btn-primary" href="{{ route('login') }}">ورود و ادامه رزرو</a><span class="d-block mt-2">برای ثبت نوبت، ابتدا شماره موبایل خود را تأیید کنید.</span></p>
@else
<form method="post" action="{{ $editing ? route('appointments.reschedule',$editing->reference) : route('appointments.store') }}" data-booking-form data-submit-message="در حال بررسی ظرفیت و ثبت درخواست…">
@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)\Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="start_at" value="" data-booking-start>
@if($editing)<input type="hidden" name="expected_version" value="{{ $editing->version }}">
@else<input type="hidden" name="service_id" value="" data-booking-service><input type="hidden" name="expected_price_irr" value="" data-booking-price>@endif
<fieldset data-patient-step>
<legend class="h5" tabindex="-1" data-patient-heading>{{ $editing ? '۲. تأیید تغییر زمان' : '۲. مشخصات بیمار' }}</legend>
@if($editing)<p>تعرفه و مدت اولیه حفظ و مهلت پرداخت تمدید نمی‌شود.</p>@else
@if($patients->isNotEmpty())<label class="d-block mb-3">این نوبت برای چه کسی است؟<select class="form-select" name="patient_id" data-patient-choice><option value="">بیمار جدید</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->name }}</option>@endforeach</select></label>@else<input type="hidden" name="patient_id" value="">@endif
<label class="d-block mb-3">نام و نام خانوادگی بیمار<input class="form-control" name="patient_name" maxlength="150" value="{{ old('patient_name') }}" data-patient-name required autocomplete="name"></label>
@if($actor->staff())<label class="d-block mb-3">موبایل رزروکننده نوبت تلفنی<input class="form-control" name="booker_mobile" inputmode="tel" maxlength="11" value="{{ old('booker_mobile') }}" required dir="ltr"></label>@endif
<details class="ux-secondary mb-3" @if(old('contact_mobile') || old('discount_code')) open @endif><summary>شماره تماس دیگر یا کد تخفیف (اختیاری)</summary><label class="d-block mb-2 mt-2">موبایل تماس بیمار<input class="form-control" name="contact_mobile" inputmode="tel" maxlength="11" value="{{ old('contact_mobile') }}" dir="ltr"></label>@include('discounts.field')</details>
@endif
@if($actor->can('appointments.override_capacity'))<input type="hidden" name="override_capacity" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="override_capacity" value="1"> ثبت استثنایی خارج از ظرفیت با مسئولیت اپراتور</label>@endif
@if($actor->staff())<label class="d-block mb-3">دلیل استثنا یا تغییر (برای استثنا الزامی)<input class="form-control" name="reason" maxlength="500" value="{{ old('reason') }}"></label>@endif
<p class="form-text">در مرحله بعد، نوبت موقت و مبلغ را می‌بینید. نوبت عادی پس از تسویه کامل قطعی می‌شود.</p>
<button class="btn btn-primary w-100" type="submit" data-booking-submit disabled>{{ $editing ? 'ثبت تغییر زمان' : 'ادامه و بررسی نوبت' }}</button>
<p data-form-status role="status" aria-live="polite"></p>
</fieldset>
</form>
@endif
