<nav class="app-navigation" aria-label="دسترسی بخش‌ها">
    <span class="app-nav-caption">حساب و نوبت‌ها</span>
    <a href="{{ route('dashboard') }}" @class(['app-nav-link', 'active' => request()->routeIs('dashboard', 'panel.*')]) @if(request()->routeIs('dashboard', 'panel.*')) aria-current="page" @endif>نمای کلی حساب</a>
    <a href="{{ route('calendar') }}" @class(['app-nav-link', 'active' => request()->routeIs('calendar')])>تقویم خدمات</a>
    <a href="{{ route('appointments.index') }}" @class(['app-nav-link', 'active' => request()->routeIs('appointments.*')])>نوبت‌های من</a>
    <a href="{{ route('wallet') }}" @class(['app-nav-link', 'active' => request()->routeIs('wallet')])>اعتبار و استرداد</a>
    @can('desk.view')<span class="app-nav-caption">عملیات مطب</span><a class="app-nav-link" href="{{ route('desk') }}">میز کار امروز</a>@endcan
    @can('desk.book')<a class="app-nav-link" href="{{ route('desk.create') }}">نوبت تلفنی / فوری</a>@endcan
    @can('patients.manage')<a class="app-nav-link" href="{{ route('patients.index') }}">بیماران</a>@endcan
    @can('catalog.manage')<a class="app-nav-link" href="{{ route('services.index') }}">مدیریت خدمات</a>@endcan
    @can('schedules.manage')<a class="app-nav-link" href="{{ route('schedule.index') }}">برنامه کاری</a>@endcan
    @can('reports.appointments')<a class="app-nav-link" href="{{ route('reports.index') }}">گزارش‌ها و خروجی</a>@endcan
    @can('discounts.manage')<a class="app-nav-link" href="{{ route('discounts.index') }}">تخفیف خدمات</a>@endcan
    @can('payments.review')<a class="app-nav-link" href="{{ route('finance') }}">مدیریت مالی</a>@endcan
    @can('booking.settings')<a class="app-nav-link" href="{{ route('booking.settings') }}">سیاست رزرو</a>@endcan
    @can('forms.manage')<a class="app-nav-link" href="{{ route('forms.manage') }}">فرم‌های خصوصی</a>@endcan
    @can('notifications.manage')<a class="app-nav-link" href="{{ route('notifications.manage') }}">اعلان‌های پیامکی</a>@endcan
    @can('website.manage')
        <span class="app-nav-caption">وب‌سایت مطب</span>
        <a class="app-nav-link" href="{{ route('website.pages.index') }}">صفحه‌ساز</a>
        <a class="app-nav-link" href="{{ route('website.sections.index') }}">مدیریت سکشن‌های سایت</a>
        <a class="app-nav-link" href="{{ route('website.links.index') }}">منو و فوتر سایت</a>
        <a class="app-nav-link" href="{{ route('website.articles.manage') }}">مدیریت مقالات</a>
        <a class="app-nav-link" href="{{ route('website.taxonomies.index') }}">دسته‌بندی و برچسب</a>
        <a class="app-nav-link" href="{{ route('website.theme.edit') }}">رنگ‌بندی سایت</a>
    @endcan
</nav>
