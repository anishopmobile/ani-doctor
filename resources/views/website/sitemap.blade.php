@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('website.home') }}</loc></url>
    <url><loc>{{ route('website.about') }}</loc></url>
    <url><loc>{{ route('website.contact') }}</loc></url>
    <url><loc>{{ route('website.policies') }}</loc></url>
    <url><loc>{{ route('website.privacy') }}</loc></url>
    <url><loc>{{ route('website.services.index') }}</loc></url>
    <url><loc>{{ route('website.articles.index') }}</loc></url>
    @foreach($pages as $page)
        <url><loc>{{ route('website.pages.show', $page->slug) }}</loc><lastmod>{{ $page->updated_at->toAtomString() }}</lastmod></url>
    @endforeach
    @foreach($services as $service)
        <url>
            <loc>{{ route('website.services.show', $service->id) }}</loc>
            @if($service->updated_at)<lastmod>{{ $service->updated_at->toAtomString() }}</lastmod>@endif
        </url>
    @endforeach
    @foreach($articles as $article)
        <url>
            <loc>{{ route('website.articles.show', $article->slug) }}</loc>
            @if($article->updated_at)<lastmod>{{ $article->updated_at->toAtomString() }}</lastmod>@endif
        </url>
    @endforeach
</urlset>
