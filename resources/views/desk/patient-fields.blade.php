<label class="d-block">نام و نام خانوادگی<input class="form-control" name="name" value="{{ old('name', $patient?->name) }}" maxlength="150" required></label>
<label class="d-block">موبایل تماس (اختیاری)<input class="form-control" name="contact_mobile" value="{{ old('contact_mobile', $patient?->contact_mobile) }}" dir="ltr" inputmode="tel" maxlength="11"></label>
<label class="d-block">شماره پرونده (اختیاری و یکتا)<input class="form-control" name="file_number" value="{{ old('file_number', $patient?->file_number) }}" maxlength="64"></label>
