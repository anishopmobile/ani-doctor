@php
    $asset = isset($block['asset_id']) ? $pageAssets->get($block['asset_id']) : null;
    $media = $asset?->media;
@endphp
<section id="{{ $block['anchor'] ?? 'block-'.$block['id'] }}" class="page-block page-layout-{{ $block['layout'] }} cms-{{ $block['background'] }} block-{{ $block['type'] }}" aria-label="{{ $block['heading'] ?? 'بخش صفحه' }}">
    @if($block['type'] === 'spacer')
        <div class="page-spacer page-spacer-{{ $block['height'] ?? 'medium' }}" aria-hidden="true"></div>
    @else
        <div class="page-block-copy">
            @if(!empty($block['eyebrow']))<p class="eyebrow">{{ $block['eyebrow'] }}</p>@endif
            @if(!empty($block['heading']))<h2>{{ $block['heading'] }}</h2>@endif
            @if(!empty($block['text']))<p class="preserve-lines">{{ $block['text'] }}</p>@endif
            @if(!empty($block['button_url']))<a class="btn-outline-site" href="{{ $block['button_url'] }}">{{ $block['button_label'] }}</a>@endif
        </div>
        @if($media)<img class="cms-media" src="{{ route('website.media.show', $media) }}" alt="{{ $media->alt_text }}" loading="lazy">@endif
        @if($block['type'] === 'services')
            <div class="service-grid page-block-full">
                @forelse($pageServices->take($block['count'] ?? 6) as $service)
                    <article class="service-card"><h3>{{ $service->name }}</h3><a href="{{ route('website.services.show', $service) }}">جزئیات و دریافت نوبت</a></article>
                @empty<p>در حال حاضر خدمتی برای رزرو آنلاین موجود نیست.</p>@endforelse
            </div>
        @elseif($block['type'] === 'articles')
            <div class="service-grid page-block-full">
                @forelse($pageArticles->take($block['count'] ?? 6) as $article)
                    <article class="service-card"><h3>{{ $article->title }}</h3><p>{{ $article->summary }}</p><a href="{{ route('website.articles.show', $article->slug) }}">مطالعه مقاله</a></article>
                @empty<p>هنوز مقاله‌ای منتشر نشده است.</p>@endforelse
            </div>
        @elseif($block['type'] === 'contact')
            <address class="page-block-full"><p>{{ $websiteTheme['contact_address'] }}</p><a href="tel:{{ $websiteTheme['contact_phone'] }}" dir="ltr">{{ $websiteTheme['contact_phone'] }}</a></address>
        @elseif($block['type'] === 'features')
            <div class="service-grid page-block-full">@foreach($block['items'] as $item)<article><h3>{{ $item['title'] }}</h3><p class="preserve-lines">{{ $item['text'] ?? '' }}</p></article>@endforeach</div>
        @elseif($block['type'] === 'faq')
            <div class="page-block-full">@foreach($block['items'] as $item)<details class="page-faq"><summary>{{ $item['title'] }}</summary><p class="preserve-lines">{{ $item['text'] ?? '' }}</p></details>@endforeach</div>
        @endif
    @endif
</section>
