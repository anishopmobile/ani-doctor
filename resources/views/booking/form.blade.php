@if(!$actor)
<p class="alert alert-info"><a href="{{ route('login') }}">ورود یا تأیید شماره مهمان با OTP</a> برای ثبت رزرو لازم است. پس از تأیید به تقویم برگردید.</p>
@else
<form class="border rounded p-3 mt-4" method="post" action="{{ $editing ? route('appointments.reschedule',$editing->reference) : route('appointments.store') }}" data-booking-form data-submit-message="در حال بررسی ظرفیت و ثبت درخواست…">
@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)\Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="start_at" value="" data-booking-start>
@if($editing)
<input type="hidden" name="expected_version" value="{{ $editing->version }}">
<p>تغییر زمان نوبت؛ تعرفه و مدت اولیه حفظ و مهلت پرداخت تمدید نمی‌شود.</p>
@else
<input type="hidden" name="service_id" value="" data-booking-service>
<input type="hidden" name="expected_price_irr" value="" data-booking-price>
<h2 class="h5">مشخصات بیمار و رزروکننده</h2>
<label class="d-block mb-2">بیمار<select class="form-select" name="patient_id" data-patient-choice><option value="">بیمار جدید</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->name }}</option>@endforeach</select></label>
<label class="d-block mb-2">نام و نام خانوادگی بیمار جدید<input class="form-control" name="patient_name" maxlength="150" value="{{ old('patient_name') }}" data-patient-name required></label>
<label class="d-block mb-2">موبایل تماس بیمار (اختیاری)<input class="form-control" name="contact_mobile" inputmode="tel" maxlength="11" value="{{ old('contact_mobile') }}" dir="ltr"></label>
@if($actor->staff())<label class="d-block mb-2">موبایل رزروکننده نوبت تلفنی<input class="form-control" name="booker_mobile" inputmode="tel" maxlength="11" value="{{ old('booker_mobile') }}" required dir="ltr"></label>@endif
@endif
@if($actor->can('appointments.override_capacity'))
<input type="hidden" name="override_capacity" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="override_capacity" value="1"> ثبت استثنایی خارج از ظرفیت با مسئولیت اپراتور</label>
@endif
@if($actor->staff())<label class="d-block mb-2">دلیل استثنا یا تغییر (برای استثنا الزامی)<input class="form-control" name="reason" maxlength="500" value="{{ old('reason') }}"></label>@endif
<p class="form-text">این درخواست فقط زمان را موقت نگه می‌دارد. پرداخت آنلاین فعلاً در دسترس نیست و تا تکمیل آن نوبت قطعی نمی‌شود.</p>
<button class="btn btn-primary" type="submit" data-booking-submit disabled>{{ $editing ? 'ثبت تغییر زمان' : 'ثبت رزرو موقت' }}</button>
<p data-form-status role="status" aria-live="polite"></p>
</form>
@endif
