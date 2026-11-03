@extends('layouts.app')
@section('content')
<h1>بررسی رسیدهای کارت‌به‌کارت</h1>
@forelse($transfers as $transfer)<section class="ux-card mb-3"><h2 class="h5">{{ $transfer->service_title }}</h2><p>{{ app(\App\Modules\Scheduling\Application\JalaliDate::class)->format(\Carbon\CarbonImmutable::parse($transfer->start_at)) }}؛ {{ number_format($transfer->amount_irr) }} ریال</p><p>{{ $transfer->state==='manager_review' ? 'مغایرت پس از موعد یا حضور؛ نیازمند بررسی مدیر، بدون لغو خودکار' : 'در انتظار بررسی رسید' }}</p><a class="btn btn-outline-primary" href="{{ route('appointments.show',$transfer->appointment_reference) }}">مشاهده نوبت و رسید</a></section>
@empty<p>رسیدی در انتظار بررسی نیست.</p>@endforelse
{{ $transfers->links() }}
@endsection
