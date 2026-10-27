@if($websiteLogo)
    <img class="brand-logo" src="{{ route('website.media.show', $websiteLogo) }}" alt="{{ $websiteLogo->alt_text }}" width="56" height="56">
@else
    <span class="brand-mark" aria-hidden="true"><x-ui-icon name="heart"/></span>
@endif
