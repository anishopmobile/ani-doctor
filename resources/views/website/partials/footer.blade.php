<footer class="website-footer"><div class="container footer-inner">
    <div><strong>{{ $websiteTheme['brand_name'] }}</strong><p>{{ $websiteTheme['footer_text'] ?: $websiteTheme['brand_subtitle'] }}</p></div>
    <nav aria-label="دسترسی فوتر">@forelse($footerLinks as $link)<a href="{{ $link->url }}" @if($link->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>@empty<a href="{{ route('website.home') }}">صفحه اصلی</a><a href="{{ route('website.services.index') }}">خدمات</a><a href="{{ route('website.articles.index') }}">مقالات</a><a href="{{ route('calendar') }}">نوبت‌دهی</a><a href="{{ route('website.about') }}">درباره پزشک</a><a href="{{ route('website.contact') }}">تماس با مطب</a>@endforelse</nav>
    <p class="footer-copyright">© {{ substr(app(App\Modules\Scheduling\Application\JalaliDate::class)->format(now()), 0, 4) }} {{ $websiteTheme['brand_name'] }}</p>
</div></footer>
