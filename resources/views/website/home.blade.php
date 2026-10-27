<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $websiteTheme['seo_description'] }}">
    @include('website.partials.seo')
    <title>{{ $websiteTheme['brand_name'] }} | {{ $websiteTheme['brand_subtitle'] }}</title>
    <link rel="sitemap" type="application/xml" href="{{ route('website.sitemap') }}">
    @unless(request()->hasAny(['category', 'tag', 'page']))
        <link rel="canonical" href="{{ url()->current() }}">
    @endunless
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/website.css') }}">
    @include('website.partials.theme-css')
    <script defer src="{{ asset('assets/website-slider.js') }}"></script>
</head>
<body>
<a class="visually-hidden-focusable" href="#main">رفتن به محتوای اصلی</a>
<header class="website-header">
    <div class="container header-inner">
        <a class="brand" href="{{ route('website.home') }}" aria-label="صفحه اصلی {{ $websiteTheme['brand_name'] }}"><span class="brand-mark" aria-hidden="true">✿</span><span>{{ $websiteTheme['brand_name'] }}<small>{{ $websiteTheme['brand_subtitle'] }}</small></span></a>
        <nav aria-label="ناوبری اصلی" class="main-nav">
            @forelse($headerLinks as $link)
                <a href="{{ $link->url }}" @if($link->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>
            @empty
                <a href="#main">خانه</a><a href="#about">درباره پزشک</a><a href="{{ route('website.services.index') }}">خدمات</a><a href="{{ route('website.articles.index') }}">مقالات</a><a href="#contact">تماس با ما</a>
            @endforelse
        </nav>
        <a class="btn-primary-site" href="{{ route('calendar') }}">رزرو نوبت آنلاین <span aria-hidden="true">◷</span></a>
    </div>
</header>
<main id="main">
    <section class="hero">
        <div class="container hero-content">
            <div class="hero-copy">
                @if($slides->isNotEmpty())
                    <div class="website-slider" data-website-slider aria-roledescription="اسلایدشو" aria-label="معرفی خدمات مطب">
                        <div class="website-slides" aria-live="off">
                            @foreach($slides as $slide)
                                <article class="website-slide" data-website-slide @if(!$loop->first) hidden @endif>
                                    @if($slide->subtitle)<p class="eyebrow">{{ $slide->subtitle }}</p>@endif
                                    @if($loop->first)<h1>{{ $slide->title }}</h1>@else<h2 class="slide-heading">{{ $slide->title }}</h2>@endif
                                    @if($slide->body)<p class="lead slide-copy">{{ $slide->body }}</p>@endif
                                    @if($slide->media)<img class="cms-media" src="{{ route('website.media.show', $slide->media->id) }}" alt="{{ $slide->media->alt_text }}" loading="lazy">@endif
                                    <div class="hero-actions">
                                        @if($slide->button_url && $slide->button_text)
                                            <a class="btn-primary-site" href="{{ $slide->button_url }}">{{ $slide->button_text }}</a>
                                        @endif
                                        <a class="btn-outline-site" href="{{ route('calendar') }}">رزرو نوبت آنلاین</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @if($slides->count() > 1)
                            <div class="slide-controls" aria-label="کنترل اسلایدها">
                                <button type="button" class="slide-control" data-slide-prev aria-label="اسلاید قبلی">قبلی</button>
                                <span class="slide-counter" data-slide-counter role="status" aria-live="polite">۱ از {{ $slides->count() }}</span>
                                <button type="button" class="slide-control" data-slide-next aria-label="اسلاید بعدی">بعدی</button>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="eyebrow">خانم {{ $websiteTheme['brand_name'] }}</p>
                    <p class="hero-subtitle">{{ $websiteTheme['brand_subtitle'] }}</p>
                    <h1>مراقبت تخصصی <em>زنان</em>، بارداری و نازایی</h1>
                    <p class="lead">ارائه خدمات تخصصی سلامت زنان در محیطی آرام و محترمانه. برای مشاهده زمان‌های قابل رزرو، وارد تقویم آنلاین مطب شوید.</p>
                    <div class="hero-actions"><a class="btn-primary-site" href="{{ route('calendar') }}">رزرو نوبت آنلاین</a><a class="btn-outline-site" href="#services">مشاهده خدمات</a></div>
                @endif
            </div>
            <div class="hero-art" aria-hidden="true"><span class="art-flower">✿</span><span class="art-heart">♡</span><span class="art-label">همراه شما در مسیر سلامتی</span></div>
        </div>
        <div class="container hero-highlights">
            <div><strong>نوبت‌دهی آنلاین</strong><span>با مشاهده تقویم و ساعات آزاد</span></div>
            <div><strong>پیگیری نوبت</strong><span>از طریق سامانه فعلی مطب</span></div>
            <div><strong>مشاوره تخصصی</strong><span>با رعایت حریم خصوصی</span></div>
        </div>
    </section>
    @foreach($sections as $section)
    <section class="section cms-section cms-{{ $section->background }}" aria-label="{{ $section->title }}">
        <div class="container">
            @if($section->subtitle)<p class="eyebrow">{{ $section->subtitle }}</p>@endif
            <h2>{{ $section->title }}</h2>
            @if($section->body)<p class="cms-copy">{{ $section->body }}</p>@endif
            @if($section->media)<img class="cms-media" src="{{ route('website.media.show', $section->media->id) }}" alt="{{ $section->media->alt_text }}" loading="lazy">@endif
            @if($section->button_url && $section->button_text)<a class="btn-outline-site" href="{{ $section->button_url }}">{{ $section->button_text }}</a>@endif
        </div>
    </section>
    @endforeach
    <section class="section about-section" id="about">
        <div class="container about-grid">
            <div class="about-symbol" aria-hidden="true">✿</div>
            <div><p class="eyebrow">درباره پزشک</p><h2>درباره خانم {{ $websiteTheme['brand_name'] }}</h2><p>{{ $websiteTheme['brand_subtitle'] }} با رویکردی مبتنی بر احترام، آرامش و حفظ حریم خصوصی مراجعان.</p><p>برای آشنایی با خدمات قابل رزرو از فهرست خدمات و تقویم آنلاین مطب استفاده کنید.</p><a class="btn-outline-site" href="#contact">اطلاعات تماس و مطب</a></div>
        </div>
    </section>
    <section class="section services-section" id="services">
        <div class="container">
            <p class="eyebrow text-center">خدمات مطب</p><h2 class="text-center">خدمات قابل رزرو</h2>
            <p class="section-intro">فهرست زیر مستقیماً از خدمات فعال و قابل رزرو در سامانه مطب دریافت می‌شود.</p>
            <div class="service-grid">
            @forelse($services as $service)
                <article class="service-card"><span class="service-icon" aria-hidden="true">✿</span><h3>{{ $service->name }}</h3><p>جزئیات زمان‌های قابل رزرو را در تقویم مشاهده کنید.</p><a href="{{ route('website.services.show', $service->id) }}">جزئیات خدمت <span aria-hidden="true">←</span></a></article>
            @empty
                <p class="empty-services">در حال حاضر خدمتی برای رزرو اینترنتی منتشر نشده است.</p>
            @endforelse
            </div>
            <p class="text-center mt-4"><a class="btn-outline-site" href="{{ route('website.services.index') }}">مشاهده همه خدمات</a></p>
        </div>
    </section>
    <section class="section why-section"><div class="container"><h2 class="text-center">چرا مطب {{ $websiteTheme['brand_name'] }}؟</h2><div class="why-grid"><div><strong>حریم خصوصی</strong><span>احترام به محرمانگی اطلاعات مراجعان</span></div><div><strong>رزرو منظم</strong><span>انتخاب از بین زمان‌های قابل رزرو</span></div><div><strong>پیگیری آسان</strong><span>دسترسی به وضعیت نوبت در سامانه</span></div></div></div></section>
    <section class="section appointment-section" id="appointment"><div class="container appointment-panel"><div><p class="eyebrow">رزرو نوبت آنلاین</p><h2>زمان مناسب خود را انتخاب کنید</h2><p>تقویم شمسی و ساعات آزاد از سامانه اصلی نوبت‌دهی مطب دریافت می‌شوند. نوبت فقط پس از طی مراحل تأیید و پرداخت مربوطه قطعی است.</p></div><a class="btn-primary-site" href="{{ route('calendar') }}">ورود به تقویم نوبت‌دهی</a></div></section>
    <section class="section contact-section" id="contact"><div class="container"><h2>اطلاعات تماس و موقعیت مطب</h2><div class="contact-grid"><div><h3>آدرس مطب</h3><p>{{ $websiteTheme['contact_address'] }}</p><h3>شماره تماس</h3><p><a href="tel:{{ $websiteTheme['contact_phone'] }}" dir="ltr">{{ $websiteTheme['contact_phone'] }}</a></p><h3>وب‌سایت</h3><p><a href="https://drzahrabagheri.ir" rel="noopener noreferrer">drzahrabagheri.ir</a></p></div><div class="contact-cta"><span aria-hidden="true">♡</span><p>سلامتی شما برای ما ارزشمند است.</p><a class="btn-primary-site" href="{{ route('calendar') }}">دریافت نوبت</a></div></div></div></section>
</main>
<footer class="website-footer"><div class="container footer-inner"><div><strong>{{ $websiteTheme['brand_name'] }}</strong><p>{{ $websiteTheme['brand_subtitle'] }}</p></div><nav aria-label="دسترسی فوتر">@forelse($footerLinks as $link)
            <a href="{{ $link->url }}" @if($link->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>
            @empty
            <a href="#about">درباره پزشک</a><a href="#services">خدمات</a><a href="{{ route('calendar') }}">نوبت‌دهی آنلاین</a><a href="#contact">تماس با ما</a>
            @endforelse</nav><p>© {{ now()->year }} {{ $websiteTheme['brand_name'] }}</p></div></footer>
</body>
</html>
