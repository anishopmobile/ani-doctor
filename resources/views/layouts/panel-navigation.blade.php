<nav class="app-navigation" aria-label="دسترسی بخش‌ها">
    <span class="app-nav-caption">حساب و نوبت‌ها</span>
    <a href="{{ route('dashboard') }}" @class(['app-nav-link', 'active' => request()->routeIs('dashboard', 'panel.*')]) @if(request()->routeIs('dashboard', 'panel.*')) aria-current="page" @endif><x-ui-icon name="grid"/><span>نمای کلی حساب</span></a>
    <a href="{{ route('calendar') }}" @class(['app-nav-link', 'active' => request()->routeIs('calendar')])><x-ui-icon name="calendar"/><span>تقویم خدمات</span></a>
    <a href="{{ route('appointments.index') }}" @class(['app-nav-link', 'active' => request()->routeIs('appointments.*')])><x-ui-icon name="file"/><span>نوبت‌های من</span></a>
    <a href="{{ route('wallet') }}" @class(['app-nav-link', 'active' => request()->routeIs('wallet')])><x-ui-icon name="wallet"/><span>اعتبار و استرداد</span></a>
    @can('desk.view')<span class="app-nav-caption">عملیات مطب</span><a class="app-nav-link" href="{{ route('desk') }}"><x-ui-icon name="grid"/><span>میز کار امروز</span></a>@endcan
    @can('desk.book')<a class="app-nav-link" href="{{ route('desk.create') }}"><x-ui-icon name="calendar"/><span>نوبت تلفنی / فوری</span></a>@endcan
    @can('patients.manage')<a class="app-nav-link" href="{{ route('patients.index') }}"><x-ui-icon name="users"/><span>بیماران</span></a>@endcan
    @can('catalog.manage')<a class="app-nav-link" href="{{ route('services.index') }}"><x-ui-icon name="heart"/><span>مدیریت خدمات</span></a>@endcan
    @can('schedules.manage')<a class="app-nav-link" href="{{ route('schedule.index') }}"><x-ui-icon name="calendar"/><span>برنامه کاری</span></a>@endcan
    @can('reports.appointments')<a class="app-nav-link" href="{{ route('reports.index') }}"><x-ui-icon name="chart"/><span>گزارش‌ها و خروجی</span></a>@endcan
    @can('discounts.manage')<a class="app-nav-link" href="{{ route('discounts.index') }}"><x-ui-icon name="wallet"/><span>تخفیف خدمات</span></a>@endcan
    @can('payments.review')<a class="app-nav-link" href="{{ route('finance') }}"><x-ui-icon name="wallet"/><span>مدیریت مالی</span></a>@endcan
    @can('booking.settings')<a class="app-nav-link" href="{{ route('booking.settings') }}"><x-ui-icon name="settings"/><span>سیاست رزرو</span></a>@endcan
    @can('forms.manage')<a class="app-nav-link" href="{{ route('forms.manage') }}"><x-ui-icon name="file"/><span>فرم‌های خصوصی</span></a>@endcan
    @can('notifications.manage')<a class="app-nav-link" href="{{ route('notifications.manage') }}"><x-ui-icon name="file"/><span>اعلان‌های پیامکی</span></a>@endcan
    @can('website.manage')
        <span class="app-nav-caption">وب‌سایت مطب</span>
        <a class="app-nav-link" href="{{ route('website.pages.index') }}"><x-ui-icon name="grid"/><span>صفحه‌ساز</span></a>
        <a class="app-nav-link" href="{{ route('website.sections.index') }}"><x-ui-icon name="grid"/><span>مدیریت سکشن‌های سایت</span></a>
        <a class="app-nav-link" href="{{ route('website.links.index') }}"><x-ui-icon name="settings"/><span>منو و فوتر سایت</span></a>
        <a class="app-nav-link" href="{{ route('website.articles.manage') }}"><x-ui-icon name="file"/><span>مدیریت مقالات</span></a>
        <a class="app-nav-link" href="{{ route('website.taxonomies.index') }}"><x-ui-icon name="file"/><span>دسته‌بندی و برچسب</span></a>
        <a class="app-nav-link" href="{{ route('website.theme.edit') }}"><x-ui-icon name="settings"/><span>رنگ‌بندی سایت</span></a>
    @endcan
</nav>
