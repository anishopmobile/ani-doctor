@extends('layouts.app')
@section('content')
<h1 class="h3">اعتبار و استرداد</h1>
<p>اعتبار به شماره تأییدشده رزروکننده تعلق دارد؛ برای بیماران مختلف همین شماره قابل مصرف است.</p>
<p>موجودی: {{ number_format($summary['balance_irr']) }} ریال؛ رزروشده برای پرداخت: {{ number_format($summary['reserved_irr']) }} ریال؛ قابل مصرف: {{ number_format($summary['available_irr']) }} ریال.</p>
<h2 class="h4">درخواست استرداد بانکی</h2>
<p>مبلغ درخواست از اعتبار قابل مصرف کنار گذاشته می‌شود. ثبت درخواست به معنی انتقال وجه نیست. مدیر پس از بررسی و انتقال خارج از سامانه، شماره پیگیری را ثبت می‌کند. اطلاعات بانکی را فقط از مسیر امن اعلام‌شده مطب ارائه کنید.</p>
<form method="post" action="{{ route('wallet.refund') }}" data-confirm="مبلغ از اعتبار قابل مصرف جدا و برای بررسی استرداد ثبت شود؟">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>مبلغ (ریال)<input class="form-control" type="number" min="1" max="{{ $summary['available_irr'] }}" name="amount_irr" required></label>
<label>دلیل درخواست<input class="form-control" name="reason" maxlength="500" required></label><button class="btn btn-primary mt-2">ثبت درخواست بررسی</button></form>
<h2 class="h4 mt-4">گردش اعتبار</h2>
@forelse($entries as $entry)<p>{{ number_format($entry->amount_irr) }} ریال — {{ ['cancellation'=>'برگشت لغو نوبت','booking_payment'=>'مصرف در رزرو','unallocated_payment'=>'پرداخت تخصیص‌نیافته','refund_reserved'=>'کنارگذاری برای استرداد','refund_reversed'=>'برگشت درخواست ردشده'][$entry->kind] ?? 'رویداد مالی' }} — <bdi>{{ $entry->created_at }}</bdi> UTC</p>@empty<p>گردش اعتباری ثبت نشده است.</p>@endforelse
{{ $entries->links() }}
<h2 class="h4 mt-4">درخواست‌های استرداد</h2>
@forelse($refunds as $refund)<p><bdi>{{ $refund->reference }}</bdi>؛ {{ number_format($refund->amount_irr) }} ریال؛ {{ ['requested'=>'در انتظار بررسی','processing'=>'در حال پیگیری بانکی','rejected'=>'ردشده و اعتبار برگشته','completed'=>'انتقال طبق سند ثبت‌شده انجام شده'][$refund->status] }} @if($refund->bank_reference)؛ پیگیری: <bdi>{{ $refund->bank_reference }}</bdi>@endif</p>@empty<p>درخواستی ندارید.</p>@endforelse
@endsection
