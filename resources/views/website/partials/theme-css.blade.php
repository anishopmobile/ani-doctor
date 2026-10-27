{{-- All three values are validated as exactly #RRGGBB before persistence. --}}
<style>
:root {
    --pink: {{ $websiteTheme['primary_color'] }};
    --purple: {{ $websiteTheme['text_color'] }};
    --paper: {{ $websiteTheme['surface_color'] }};
}
.btn-primary-site { background: var(--pink); }
.btn-outline-site { border-color: var(--pink); color: var(--pink); }
.brand-mark, .eyebrow, .service-card a, .why-grid strong, .service-icon, .contact-cta > span { color: var(--pink); }
.hero h1 em { color: var(--pink); }
.service-icon { background: var(--paper); }
.slide-control { border-color: var(--pink); }
</style>
