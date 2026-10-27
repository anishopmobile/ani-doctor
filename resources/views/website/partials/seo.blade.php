<meta name="robots" content="{{ !($isPreview ?? false) && app()->environment('production') ? 'index, follow' : 'noindex, nofollow' }}">
<meta property="og:locale" content="fa_IR">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $websiteTheme['brand_name'] }}">
<meta property="og:title" content="@yield('title', e($websiteTheme['brand_name']))">
<meta property="og:description" content="@yield('meta_description', e($websiteTheme['seo_description']))">
<meta property="og:url" content="{{ $canonicalUrl ?? url()->current() }}">
