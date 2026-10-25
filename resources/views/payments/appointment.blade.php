<section class="mt-3" aria-labelledby="payment-heading">
<h2 id="payment-heading" class="h4">پرداخت و پیگیری مالی</h2>
<p>مانده بدهی این نوبت: {{ number_format($appointment->balance_due_irr) }} ریال.</p>
@if($appointment->effectiveStatus() === 'pending_payment')
<p>برای قطعی‌شدن عادی، کل مبلغ باید با پرداخت و اعتبار تسویه شود. لغو پرداخت‌شده، مبلغ مجاز را به اعتبار شماره رزروکننده برمی‌گرداند.</p>
<form method="post" action="{{ route('payments.initiate', $appointment->reference) }}">@csrf
<input type="hidden" name="expected_version" value="{{ $appointment->version }}"><input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label for="credit-irr">اعتبار مصرفی (ریال)، حداکثر موجودی قابل مصرف شما: {{ number_format($wallet['available_irr']) }}</label>
<input id="credit-irr" class="form-control" name="credit_irr" type="number" min="0" max="{{ min($wallet['available_irr'], $appointment->price_irr) }}" value="0" required>
<p class="form-text">باقی‌مانده با زرین‌پال پرداخت می‌شود. اگر کل مبلغ با اعتبار پوشش داده شود، به درگاه نمی‌روید. در صورت نتیجه مبهم، دوباره پرداخت نکنید.</p>
<button class="btn btn-primary mt-2" data-hold-action>پرداخت و تسویه کامل</button></form>
@if($emergencyActive)
<form class="mt-3" method="post" action="{{ route('payments.emergency', $appointment->reference) }}" data-confirm="این نوبت با بدهی و پرداخت در مطب قطعی می‌شود. ادامه می‌دهید؟">@csrf
<input type="hidden" name="expected_version" value="{{ $appointment->version }}"><input type="hidden" name="reason" value="انتخاب پرداخت در مطب در بازه اضطراری فعال">
<button class="btn btn-outline-primary" data-hold-action>ثبت قطعی با بدهی؛ پرداخت در مطب</button></form>
@endif
@endif
@can('payments.collect')
@if($appointment->status === 'confirmed' && $appointment->payment_status === 'unpaid' && $appointment->balance_due_irr > 0)
<form method="post" action="{{ route('payments.collect', $appointment->reference) }}" class="mt-3" data-confirm="دریافت حضوری کل بدهی ثبت شود؟">@csrf
<input type="hidden" name="receipt" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="amount_irr" value="{{ $appointment->balance_due_irr }}">
<label>روش دریافت<select class="form-select" name="method"><option value="cash">نقدی</option><option value="card">کارت‌خوان</option><option value="transfer">انتقال بانکی</option></select></label>
<label>توضیح و شماره رسید اختیاری<input class="form-control" name="reason" maxlength="500" required></label>
<button class="btn btn-primary mt-2">ثبت دریافت حضوری {{ number_format($appointment->balance_due_irr) }} ریال</button></form>
@endif
@endcan
@foreach($attempts as $attempt)
<p class="mt-2">تلاش پرداخت <bdi>{{ $attempt->reference }}</bdi>: {{ ['requesting'=>'در حال ایجاد؛ از پرداخت مجدد خودداری کنید','redirected'=>'ارسال به درگاه؛ منتظر نتیجه','uncertain'=>'نیازمند بررسی؛ دوباره پرداخت نکنید','failed'=>'ناموفق؛ در مهلت معتبر می‌توانید دوباره تلاش کنید','settled'=>'تسویه کامل و تخصیص به نوبت','credited'=>'وجه تخصیص نیافت؛ به اعتبار شماره رزروکننده برگشت'][$attempt->state] ?? 'نیازمند بررسی' }}</p>
@endforeach
<a href="{{ route('wallet') }}">اعتبار و درخواست استرداد</a>
</section>
