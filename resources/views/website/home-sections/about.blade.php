<section class="section about-section" id="about"><div class="container about-grid">
    @if($websitePortrait)<img class="doctor-portrait" src="{{ route('website.media.show', $websitePortrait) }}" alt="{{ $websitePortrait->alt_text }}" loading="lazy">@else<div class="about-symbol" aria-hidden="true"><x-ui-icon name="heart"/></div>@endif
    <div><p class="eyebrow">درباره {{ $websiteTheme['brand_name'] }}</p><h2>{{ $websiteTheme['about_title'] }}</h2><p>{{ $websiteTheme['brand_subtitle'] }}</p><p class="preserve-lines">{{ $websiteTheme['about_text'] }}</p><a class="btn-outline-site" href="{{ route('website.services.index') }}">مشاهده خدمات <x-ui-icon name="arrow"/></a></div>
</div></section>
