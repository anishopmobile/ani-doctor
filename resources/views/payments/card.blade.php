@php($transfer = \App\Modules\Payments\Infrastructure\CardTransfer::where('appointment_id', $appointment->id)->first())
@php($cardEnabled = \Illuminate\Support\Facades\DB::table('card_transfer_settings')->where('id',1)->value('enabled'))
<section class="ux-card"><h2 class="h4">پرداخت کارت‌به‌کارت</h2>
@can('payments.settings')<p><a href="{{ route('card.settings') }}">تنظیم کارت مقصد</a></p>@endcan
@if(!$transfer && $cardEnabled && $appointment->effectiveStatus()==='pending_payment')
<p>ارسال رسید تا پایان مهلت، نوبت را قطعی می‌کند؛ دریافت وجه بعداً توسط مطب بررسی می‌شود.</p>
<form method="post" action="{{ route('card.begin',$appointment->reference) }}">@csrf<input type="hidden" name="expected_version" value="{{ $appointment->version }}"><button class="btn btn-outline-primary">انتخاب کارت‌به‌کارت</button></form>
@elseif($transfer)
<p>مبلغ: {{ number_format($transfer->amount_irr) }} ریال؛ کارت مقصد: <bdi>{{ $transfer->card_number }}</bdi>؛ صاحب کارت: {{ $transfer->holder }}</p>
<p role="status">{{ ['awaiting_receipt'=>'در انتظار ارسال رسید در مهلت رزرو','pending_review'=>'کارت‌به‌کارت — در انتظار تأیید منشی','approved'=>'پرداخت‌شده با تأیید دستی','rejected'=>'پرداخت رد شد؛ نوبت لغو شد','manager_review'=>'پرداخت رد شد؛ تصمیم درباره نوبت با مدیر است','cancelled'=>'رزرو لغو یا منقضی شده است'][$transfer->state] ?? 'نیازمند بررسی' }}</p>
@if($transfer->state==='awaiting_receipt' && $appointment->effectiveStatus()==='pending_payment')
<p>پایان مهلت (تهران): {{ $transfer->deadline->setTimezone('Asia/Tehran')->format('H:i:s') }}؛ فقط JPEG، PNG یا PDF تا ۵ مگابایت.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('card.submit',$appointment->reference) }}">@csrf<label>تصویر رسید<input class="form-control" type="file" name="receipt" accept="image/jpeg,image/png,application/pdf" required></label><button class="btn btn-primary">ارسال رسید و قطعی‌کردن نوبت</button></form>
@endif
@if($transfer->receipt_path && (!$actor->staff() || $actor->can('payments.card_review')))<a href="{{ route('card.receipt',$appointment->reference) }}">دریافت خصوصی رسید</a>@endif
@can('payments.card_review')
@if($transfer->duplicate_receipt || ($transfer->receipt_hash && \App\Modules\Payments\Infrastructure\CardTransfer::where('receipt_hash',$transfer->receipt_hash)->count()>1))<p class="alert alert-warning">این فایل قبلاً برای رزرو دیگری دریافت شده است؛ تطبیق بانکی لازم است.</p>@endif
@if($transfer->state==='pending_review')<form method="post" action="{{ route('card.review',$appointment->reference) }}">@csrf<label>دلیل بررسی<input class="form-control" name="reason" maxlength="500" required></label><button class="btn btn-primary" name="decision" value="approve">تأیید دریافت وجه</button> <button class="btn btn-outline-danger" name="decision" value="reject">رد رسید</button></form>@endif
@if($transfer->state==='manager_review')<p class="alert alert-warning">به دلیل زمان مراجعه یا سابقه حضور، نوبت خودکار لغو نشده است. مدیر باید مغایرت مالی و سابقه مراجعه را بررسی کند.</p>@endif
@endcan
@endif
</section>
