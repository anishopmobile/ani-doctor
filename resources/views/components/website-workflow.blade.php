<details class="ux-site-map" data-site-map><summary>رفتن به بخش دیگر مدیریت سایت</summary>
<nav class="ux-task-links" aria-label="کارهای مدیریت سایت">
<a href="{{ route('website.theme.edit') }}">هویت، تماس و صفحه اصلی</a><a href="{{ route('website.pages.index') }}">طراحی صفحه‌ها</a><a href="{{ route('website.articles.manage') }}">مقاله‌ها</a><a href="{{ route('website.links.index') }}">منو و پیوندها</a><a href="{{ route('website.sections.index') }}">بخش‌های نمایشی</a>@can('catalog.manage')<a href="{{ route('services.index') }}">خدمت‌ها و محتوای آن‌ها</a>@endcan<a href="{{ route('website.home') }}" target="_blank" rel="noopener">مشاهده سایت عمومی ↗</a>
</nav>

</details>
