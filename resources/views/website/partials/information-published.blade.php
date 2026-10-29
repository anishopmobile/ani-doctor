@if(isset($snapshot))
    @if($snapshot['blocks'])
        <div class="info-layout info-section">
            <aside class="info-card info-sidebar"><h2>موضوعات</h2><nav class="info-toc" aria-label="موضوعات صفحه">@foreach($snapshot['blocks'] as $block)@if(!empty($block['heading']))<a href="#{{ $block['anchor'] ?? 'block-'.$block['id'] }}">{{ $block['heading'] }}</a>@endif @endforeach</nav></aside>
            <div class="info-main">@foreach($snapshot['blocks'] as $block)@include('website.pages.block', ['block' => $block])@endforeach</div>
        </div>
    @endif
@endif
