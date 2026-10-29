@extends('website.layout')
@section('title', 'برنامه هفتگی پزشک')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/weekly-schedule.css') }}">
<header class="section-heading"><p class="eyebrow">زمان و محل حضور پزشک</p><h1>برنامه هفتگی پزشک</h1><p><bdi>{{ $week['start'] }}</bdi> تا <bdi>{{ $week['end'] }}</bdi></p></header>
<p>این برنامه برای اطلاع‌رسانی حضور پزشک است. زمان‌های قابل رزرو را در تقویم نوبت‌دهی بررسی کنید.</p>
<div class="weekly-cards">
@foreach($week['days'] as $day)
    <article @class(['weekly-day', 'weekly-today' => $day['today'], 'weekly-'.$day['presence']['status']]) @if($day['today']) aria-current="date" @endif>
        <header class="weekly-day-heading"><div><h2>{{ $day['weekday'] }}</h2><time datetime="{{ $day['date'] }}">{{ $day['jalali'] }}</time></div>@if($day['today'])<span class="weekly-badge">امروز</span>@endif</header>
        <x-weekly-presence :presence="$day['presence']"/>
        @if($day['presence']['status'] === 'working')
            @if($day['location'] !== null && $day['location'] !== '')<p class="weekly-location"><x-ui-icon name="pin"/>{{ $day['location'] }}</p>@else<p>محل ویزیت اعلام نشده است.</p>@endif
            @if($day['outside_office'])<span class="weekly-badge">ویزیت خارج از مطب</span>@endif
        @endif
        @if($day['notes'] !== null && $day['notes'] !== '')<p class="weekly-note">{{ $day['notes'] }}</p>@endif
    </article>
@endforeach
</div>
@if($notes !== '')<aside class="weekly-general-notes" aria-label="توضیحات برنامه هفتگی">{{ $notes }}</aside>@endif
@endsection
