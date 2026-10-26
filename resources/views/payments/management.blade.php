@extends('layouts.app')
@section('content')
<h1 class="h3">مدیریت مالی</h1>
<p class="alert alert-warning">تنظیم اضطراری فقط برای مدت محدود فعال می‌شود و نوبت را بدهکار ثبت می‌کند. موفقیت بانکی ایجاد نمی‌کند.</p>
<p>پایان بازه اضطراری: <bdi>{{ $settings->emergency_until ?? 'غیرفعال' }}</bdi> UTC</p>
<form method="post" action="{{ route('finance.settings') }}">@csrf @method('PUT')
<label>وضعیت<select name="enabled" class="form-select"><option value="0">غیرفعال</option><option value="1">فعال برای مدت محدود</option></select></label>
<label>مدت (دقیقه، حداکثر ۱۴۴۰)<input class="form-control" name="minutes" type="number" min="1" max="1440" value="60" required></label>
<label>دلیل<input class="form-control" name="reason" maxlength="500" required></label><button class="btn btn-primary mt-2">ثبت سیاست</button></form>
<h2 class="h4 mt-4">پرداخت‌های نیازمند پیگیری</h2>
@forelse($attempts as $attempt)<article class="border rounded p-3 mb-2"><p><bdi>{{ $attempt->reference }}</bdi>؛ {{ number_format($attempt->amount_irr) }} ریال؛ {{ ['requesting'=>'ایجاد درخواست بدون نتیجه قطعی','uncertain'=>'نتیجه مبهم','credited'=>'وجه به اعتبار برگشته'][$attempt->state] ?? 'نیازمند بررسی' }}</p>
@if($attempt->state !== 'credited')<form method="post" action="{{ route('finance.reconcile', $attempt->reference) }}">@csrf<button class="btn btn-outline-primary">تأیید و استعلام مجدد از درگاه</button></form>@endif</article>@empty<p>موردی نیست.</p>@endforelse
<h2 class="h4 mt-4">بررسی استرداد</h2>
@forelse($refunds as $refund)<article class="border rounded p-3 mb-2"><p><bdi>{{ $refund->reference }}</bdi>؛ {{ number_format($refund->amount_irr) }} ریال؛ {{ ['requested'=>'در انتظار بررسی','processing'=>'در حال پیگیری بانکی','rejected'=>'ردشده','completed'=>'انتقال ثبت‌شده'][$refund->status] }}</p><p>{{ $refund->reason }}</p>
@if(in_array($refund->status, ['requested','processing'], true))
<form method="post" action="{{ route('finance.refund', $refund->reference) }}" data-confirm="تصمیم مالی و سند واردشده ثبت شود؟">@csrf
<label>تصمیم<select class="form-select" name="decision"><option value="processing">پذیرش برای پیگیری</option><option value="rejected">رد و بازگرداندن اعتبار</option>@if($refund->status === 'processing')<option value="completed">ثبت انتقال انجام‌شده با سند</option>@endif</select></label>
<label>دلیل تصمیم<input class="form-control" name="reason" maxlength="500" required></label><label>شماره پیگیری بانکی (فقط پس از انتقال واقعی)<input class="form-control" name="bank_reference" maxlength="100"></label><button class="btn btn-primary mt-2">ثبت تصمیم</button></form>@endif</article>@empty<p>درخواستی نیست.</p>@endforelse
{{ $refunds->links() }}
@endsection
