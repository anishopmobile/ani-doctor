<header class="website-header" id="public-site-top" tabindex="-1" data-public-header>
    <div class="container header-inner">
        <a class="brand" href="{{ route('website.home') }}" aria-label="صفحه اصلی {{ $websiteTheme['brand_name'] }}">@include('website.partials.brand-logo')<span>{{ $websiteTheme['brand_name'] }}<small>{{ $websiteTheme['brand_subtitle'] }}</small></span></a>
        <nav class="main-nav" id="public-navigation" aria-label="ناوبری اصلی">
            @forelse($headerLinks as $link)
                @continue(url($link->url) === route('weekly-announcements.public'))
                <a href="{{ $link->url }}" @if(url($link->url) === url()->current()) aria-current="page" @endif @if($link->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>
            @empty
                <a href="{{ route('website.home') }}" @if(request()->routeIs('website.home')) aria-current="page" @endif>خانه</a>
                <a href="{{ route('website.about') }}" @if(request()->routeIs('website.about')) aria-current="page" @endif>درباره پزشک</a>
                <a href="{{ route('website.services.index') }}" @if(request()->routeIs('website.services.*')) aria-current="page" @endif>خدمات</a>
                <a href="{{ route('website.articles.index') }}" @if(request()->routeIs('website.articles.*')) aria-current="page" @endif>مقالات</a>
                <a href="{{ route('website.contact') }}" @if(request()->routeIs('website.contact')) aria-current="page" @endif>تماس با مطب</a>
            @endforelse
            @if($websiteTheme['show_weekly_schedule'])<a href="{{ route('weekly-announcements.public') }}" @if(request()->routeIs('weekly-announcements.public')) aria-current="page" @endif>برنامه هفتگی</a>@endif
        </nav>
        <div class="header-actions"><button class="btn btn-outline-primary public-menu-toggle" type="button" data-public-menu aria-controls="public-navigation" aria-expanded="false" hidden><x-ui-icon name="grid"/>فهرست</button><a class="btn-primary-site" href="{{ route('calendar') }}"><x-ui-icon name="calendar"/>رزرو نوبت آنلاین</a></div>
    </div>
</header>
