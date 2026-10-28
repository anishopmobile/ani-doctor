@once @push('assets')<script src="{{ asset('assets/discount.js') }}" defer></script>@endpush @endonce
<details class="ux-discount my-3" @if(old('discount_code')) open @endif><summary>کد تخفیف دارید؟ <span class="text-secondary">(اختیاری)</span></summary>
<div class="pt-3" data-discount-quote data-url="{{ route('discounts.preview') }}">
<label class="d-block">کد تخفیف (اختیاری)<input class="form-control" name="discount_code" maxlength="32" value="{{ old('discount_code') }}" dir="ltr" autocomplete="off"></label>
<button class="btn btn-outline-primary mt-2" type="button" data-discount-preview>برآورد مبلغ با تخفیف</button>
<p class="form-text" role="status" aria-live="polite" data-discount-result>کد را وارد کنید و «برآورد مبلغ با تخفیف» را بزنید. مبلغ نهایی هنگام ثبت رزرو بررسی می‌شود.</p>
</div>
</details>
