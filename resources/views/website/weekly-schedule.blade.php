@extends('website.layout')
@section('title', 'برنامه هفتگی پزشک')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/weekly-poster.css') }}">
<section class="weekly-poster" aria-labelledby="weekly-poster-title">
    <header class="weekly-poster__header">
        <div class="weekly-poster__photo">
            @if($websitePortrait)<img src="{{ route('website.media.show', $websitePortrait) }}" alt="{{ $websitePortrait->alt_text }}" width="160" height="180">@else<img class="weekly-poster__art" src="{{ asset('assets/clinic-art.webp') }}" alt="{{ $websiteTheme['brand_name'] }}" width="1254" height="1254">@endif
        </div>
        <div class="weekly-poster__identity"><p class="weekly-poster__name">{{ $websiteTheme['brand_name'] }}</p><p class="weekly-poster__specialty">{{ $websiteTheme['brand_subtitle'] }}</p></div>
    </header>
    <div class="weekly-poster__decoration" aria-hidden="true"><span></span><x-ui-icon name="heart"/><span></span></div>
    <div class="weekly-poster__intro"><h1 id="weekly-poster-title"><x-ui-icon name="calendar"/>برنامه هفتگی پزشک</h1><p class="weekly-poster__range"><bdi>{{ $week['start'] }}</bdi> تا <bdi>{{ $week['end'] }}</bdi></p></div>
    <p class="weekly-poster__notice">این برنامه برای اطلاع‌رسانی حضور پزشک است. زمان‌های قابل رزرو را در تقویم نوبت‌دهی بررسی کنید.</p>
    <div class="weekly-poster__list">
@foreach($week['days'] as $day)
    <article @class(['weekly-poster__row', 'weekly-poster__row--today' => $day['today'], 'weekly-poster__row--'.$day['presence']['status']]) @if($day['today']) aria-current="date" @endif>
        <header class="weekly-poster__day"><h2>{{ $day['weekday'] }}</h2><time datetime="{{ $day['date'] }}">{{ $day['jalali'] }}</time>@if($day['today'])<span class="weekly-poster__badge">امروز</span>@endif</header>
        <div class="weekly-poster__details">
            <div class="weekly-poster__times">
                <p class="weekly-poster__status">{{ $day['status_label'] }}</p>
                @foreach($day['presence']['shifts'] as $shift)
                    <div class="weekly-poster__time"><x-ui-icon name="clock"/><div>@if($shift['label'] !== null && $shift['label'] !== '')<p class="weekly-poster__period">{{ $shift['label'] }}</p>@endif<p class="weekly-poster__hours"><bdi>{{ $shift['start'] }}</bdi><span> تا </span><bdi>{{ $shift['end'] }}</bdi></p></div></div>
                @endforeach
            </div>
            @if($day['presence']['status'] === 'working')
                <div class="weekly-poster__location"><x-ui-icon name="pin"/><div>@if($day['location'] !== null && $day['location'] !== '')<p>{{ $day['location'] }}</p>@else<p>محل ویزیت اعلام نشده است.</p>@endif @if($day['outside_office'])<span class="weekly-poster__badge">ویزیت خارج از مطب</span>@endif</div></div>
            @endif
            @if($day['notes'] !== null && $day['notes'] !== '')<p class="weekly-poster__note">{{ $day['notes'] }}</p>@endif
        </div>
    </article>
@endforeach
    </div>
    @if($notes !== '')<aside class="weekly-general-notes" aria-label="توضیحات برنامه هفتگی">{{ $notes }}</aside>@endif
    @if($websiteTheme['contact_phone'] !== '')
        <aside class="weekly-poster__contact" aria-label="تماس با مطب"><span class="weekly-poster__phone-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg></span><div><p>جهت پرسش و هماهنگی مراجعه</p><a href="tel:{{ $websiteTheme['contact_phone'] }}"><bdi>{{ $websiteTheme['contact_phone'] }}</bdi></a><p class="weekly-poster__contact-hint">با مطب تماس بگیرید.</p></div></aside>
    @endif
</section>
@endsection
