@once @push('assets')<script src="{{ asset('assets/discount.js') }}" defer></script>@endpush @endonce
<div class="border rounded p-3 my-3" data-discount-quote data-url="{{ route('discounts.preview') }}">
<label class="d-block">کد تخفیف (اختیاری)<input class="form-control" name="discount_code" maxlength="32" value="{{ old('discount_code') }}" dir="ltr" autocomplete="off"></label>
<button class="btn btn-outline-primary mt-2" type="button" data-discount-preview>برآورد مبلغ با تخفیف</button>
<p class="form-text" role="status" aria-live="polite" data-discount-result>پس از انتخاب خدمت و ساعت، می‌توانید مبلغ را برآورد کنید. سهمیه و مجوز بیمار هنگام رزرو دوباره بررسی می‌شود؛ برآورد ظرفیت یا تخفیف را نگه نمی‌دارد.</p>
</div>
