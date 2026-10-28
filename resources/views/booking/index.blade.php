@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $actor->staff() ? 'نوبت‌های مطب' : 'نوبت‌های من' }}</h1>
<a class="btn btn-primary mb-3" href="{{ route('calendar') }}">رزرو نوبت جدید</a>
@forelse($appointments as $appointment)
<article class="ux-card ux-appointment-row"><h2 class="h6">{{ $appointment->patient->name }} — {{ $appointment->service_title }}</h2><p><bdi>{{ app(\App\Modules\Scheduling\Application\JalaliDate::class)->format($appointment->start_at) }}، {{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }}</bdi> · <x-appointment-status :status="$appointment->effectiveStatus()"/></p>
@if($appointment->is_override)<span class="badge text-bg-warning">خارج از ظرفیت</span>@endif
<a class="btn btn-outline-primary" href="{{ route('appointments.show',$appointment->reference) }}">{{ $appointment->effectiveStatus() === 'pending_payment' ? 'ادامه پرداخت و قطعی‌کردن' : 'مشاهده و پیگیری نوبت' }}</a></article>
@empty<p>نوبتی متعلق به این هویت یافت نشد.</p>@endforelse
{{ $appointments->links() }}
@endsection
