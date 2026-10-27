@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $actor->staff() ? 'نوبت‌های مطب' : 'نوبت‌های من' }}</h1>
<a class="btn btn-primary mb-3" href="{{ route('calendar') }}">رزرو از تقویم</a>
@forelse($appointments as $appointment)
<article class="border rounded p-3 mb-2"><h2 class="h6">{{ $appointment->patient->name }} — {{ $appointment->service_title }}</h2><p><x-appointment-status :status="$appointment->effectiveStatus()"/></p>
@if($appointment->is_override)<span class="badge text-bg-warning">خارج از ظرفیت</span>@endif
<a href="{{ route('appointments.show',$appointment->reference) }}">جزئیات و مدیریت</a></article>
@empty<p>نوبتی متعلق به این هویت یافت نشد.</p>@endforelse
{{ $appointments->links() }}
@endsection
