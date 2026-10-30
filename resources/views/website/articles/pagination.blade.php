@if($articles->hasPages())
<nav class="articles-pagination" aria-label="صفحه‌بندی مقالات">
@if($articles->onFirstPage())<span aria-disabled="true">قبلی</span>@else<a href="{{ $articles->previousPageUrl() }}" rel="prev">قبلی</a>@endif
@foreach($articles->getUrlRange(max(1, $articles->currentPage() - 2), min($articles->lastPage(), $articles->currentPage() + 2)) as $number => $url)
@if($number === $articles->currentPage())<span aria-current="page">{{ $number }}</span>@else<a href="{{ $url }}" aria-label="صفحه {{ $number }}">{{ $number }}</a>@endif
@endforeach
@if($articles->hasMorePages())<a href="{{ $articles->nextPageUrl() }}" rel="next">بعدی</a>@else<span aria-disabled="true">بعدی</span>@endif
</nav>
@endif
