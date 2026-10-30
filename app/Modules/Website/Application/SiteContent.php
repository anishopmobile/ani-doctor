<?php

namespace App\Modules\Website\Application;

final class SiteContent
{
    public const SECTIONS = ['about' => 'معرفی پزشک', 'services' => 'خدمات', 'why' => 'روش همراهی مطب', 'faq' => 'سؤالات متداول', 'appointment' => 'دعوت به نوبت‌دهی', 'contact' => 'اطلاعات مطب'];

    public const FIELDS = [
        'hero_title' => ['معرفی نخست', 'عنوان اصلی', 180, 'مراقبت تخصصی زنان، بارداری و نازایی'],
        'hero_text' => ['معرفی نخست', 'متن معرفی', 1200, 'ارائه خدمات تخصصی سلامت زنان در محیطی آرام و محترمانه. برای مشاهده زمان‌های قابل رزرو، وارد تقویم آنلاین مطب شوید.'],
        'hero_button_label' => ['معرفی نخست', 'عنوان دکمه اصلی', 80, 'رزرو نوبت آنلاین'],
        'hero_button_url' => ['معرفی نخست', 'لینک داخلی دکمه اصلی', 255, '/calendar'],
        'hero_secondary_label' => ['معرفی نخست', 'عنوان دکمه دوم', 80, 'مشاهده خدمات'],
        'hero_secondary_url' => ['معرفی نخست', 'لینک داخلی دکمه دوم', 255, '/services'],
        'hero_art_title' => ['معرفی نخست', 'عنوان زیر تصویر معرفی', 180, 'همراه شما در مسیر سلامتی'],
        'hero_art_subtitle' => ['معرفی نخست', 'توضیح زیر تصویر معرفی', 300, 'مراقبتی همراه با آرامش و احترام'],
        'about_title' => ['معرفی پزشک', 'عنوان معرفی پزشک', 180, 'مراقبتی همراه با آرامش و احترام'],
        'about_text' => ['معرفی پزشک', 'معرفی تأییدشده پزشک', 4000, 'برای آشنایی با خدمات قابل رزرو از فهرست خدمات و تقویم آنلاین مطب استفاده کنید.'],
        'home_about_button_label' => ['درباره ما در صفحه اصلی', 'عنوان دکمه', 80, 'بیشتر درباره ما'],
        'home_about_button_url' => ['درباره ما در صفحه اصلی', 'لینک داخلی دکمه', 255, '/about'],
        'home_about_badge_title' => ['درباره ما در صفحه اصلی', 'عنوان کارت روی تصویر (اختیاری)', 100, ''],
        'home_about_badge_text' => ['درباره ما در صفحه اصلی', 'توضیح کارت روی تصویر (اختیاری)', 180, ''],
        'services_title' => ['خدمات', 'عنوان بخش خدمات', 180, 'خدمات قابل رزرو مطب'],
        'services_intro' => ['خدمات', 'توضیح بخش خدمات', 1200, 'خدمات این صفحه از فهرست فعال نوبت‌دهی دریافت می‌شوند. زمان‌های آزاد در تقویم اصلی قابل مشاهده‌اند.'],
        'home_services_all_label' => ['خدمات مطب در صفحه اصلی', 'عنوان دکمه همه خدمات', 80, 'مشاهده همه خدمات'],
        'home_services_booking_label' => ['خدمات مطب در صفحه اصلی', 'عنوان دکمه تقویم', 80, 'دریافت نوبت'],
        'home_services_badge' => ['خدمات مطب در صفحه اصلی', 'متن نشان روی تصویر مدل تیره (اختیاری)', 180, ''],
        'why_title' => ['همراهی و سؤالات', 'عنوان همراهی مطب', 180, 'همراه شما، در هر قدم'],
        'faq_title' => ['همراهی و سؤالات', 'عنوان سؤالات متداول', 180, 'پاسخ به پرسش‌های شما'],
        'appointment_title' => ['نوبت‌دهی و فوتر', 'عنوان دعوت به رزرو', 180, 'زمان مناسب خود را انتخاب کنید'],
        'appointment_text' => ['نوبت‌دهی و فوتر', 'توضیح رزرو', 1200, 'نوبت فقط پس از طی مراحل تأیید و پرداخت مربوطه قطعی است.'],
        'appointment_button_label' => ['نوبت‌دهی و فوتر', 'عنوان دکمه تقویم', 80, 'ورود به تقویم نوبت‌دهی'],
        'contact_title' => ['اطلاعات مطب', 'عنوان بخش تماس', 180, 'اطلاعات تماس و موقعیت مطب'],
        'home_contact_intro' => ['تماس در صفحه اصلی', 'توضیح بخش تماس', 1200, 'برای هماهنگی مراجعه و پرسش‌های عمومی، از راه‌های ارتباطی ثبت‌شده مطب استفاده کنید.'],
        'home_contact_badge' => ['تماس در صفحه اصلی', 'متن روی تصویر (اختیاری)', 180, ''],
        'contact_hours' => ['اطلاعات مطب', 'ساعات پاسخ‌گویی (متن اطلاع‌رسانی)', 500, ''],
        'contact_email' => ['اطلاعات مطب', 'ایمیل عمومی', 180, ''],
        'contact_website' => ['اطلاعات مطب', 'وب‌سایت رسمی HTTPS', 255, 'https://drzahrabagheri.ir'],
        'contact_map_url' => ['اطلاعات مطب', 'لینک HTTPS نقشه', 500, ''],
        'footer_text' => ['نوبت‌دهی و فوتر', 'توضیح فوتر', 500, ''],
        'footer_booking_text' => ['نوبت‌دهی و فوتر', 'توضیح زیر دکمه رزرو فوتر (اختیاری)', 300, ''],
        'footer_slogan' => ['نوبت‌دهی و فوتر', 'شعار پایانی فوتر (اختیاری)', 180, ''],
        'contact_secondary_phone' => ['اطلاعات مطب', 'شماره تماس دوم عمومی (اختیاری، لاتین)', 16, ''],
    ];

    public static function defaults(): array
    {
        $values = array_map(fn (array $field) => $field[3], self::FIELDS);
        $values['section_order'] = array_keys(self::SECTIONS);
        foreach (self::SECTIONS as $key => $label) {
            $values['show_'.$key] = $key !== 'faq';
        }
        $values['why_items'] = [
            ['title' => 'حریم خصوصی', 'text' => 'احترام به محرمانگی اطلاعات مراجعان'],
            ['title' => 'رزرو منظم', 'text' => 'انتخاب از بین زمان‌های قابل رزرو'],
            ['title' => 'پیگیری آسان', 'text' => 'دسترسی به وضعیت نوبت در سامانه'],
        ];
        $values['faq_items'] = [];
        $values['footer_features'] = $values['why_items'];
        $values['home_about_layout'] = 'classic';
        $values['home_services_layout'] = 'light';
        $values['home_contact_layout'] = 'clinic';
        $values['home_contact_form_enabled'] = true;
        $values['home_services_highlights'] = [];
        $values['home_about_stats'] = [];
        $values['home_about_features'] = [
            ['title' => 'نوبت‌دهی آنلاین', 'text' => 'انتخاب زمان از تقویم مطب'],
            ['title' => 'حریم خصوصی', 'text' => 'احترام به محرمانگی اطلاعات مراجعان'],
            ['title' => 'پیگیری نوبت', 'text' => 'دسترسی به وضعیت نوبت در سامانه'],
            ['title' => 'ارتباط با مطب', 'text' => 'راه‌های ارتباطی رسمی و اطلاعات مراجعه'],
        ];
        $values['highlight_items'] = [
            ['title' => 'نوبت‌دهی آنلاین', 'text' => 'با مشاهده تقویم و ساعات آزاد'],
            ['title' => 'پیگیری نوبت', 'text' => 'از طریق سامانه فعلی مطب'],
            ['title' => 'مشاوره تخصصی', 'text' => 'با رعایت حریم خصوصی'],
        ];

        return $values;
    }

    public static function sanitize(array $values): array
    {
        $defaults = self::defaults();
        foreach ($defaults as $key => $default) {
            if (is_bool($default) && in_array($values[$key] ?? null, ['0', '1', 0, 1], true)) {
                $values[$key] = (bool) $values[$key];
            }
            if (!array_key_exists($key, $values) || gettype($values[$key]) !== gettype($default)) {
                $values[$key] = $default;
            }
        }
        $order = array_values(array_unique(array_filter($values['section_order'], fn ($key) => is_string($key) && isset(self::SECTIONS[$key]))));
        $values['section_order'] = array_values(array_unique([...$order, ...array_keys(self::SECTIONS)]));
        if (!in_array($values['home_about_layout'], ['classic', 'dark', 'organic'], true)) {
            $values['home_about_layout'] = 'classic';
        }
        if (!in_array($values['home_services_layout'], ['light', 'dark', 'pastel'], true)) {
            $values['home_services_layout'] = 'light';
        }
        if (!in_array($values['home_contact_layout'], ['clinic', 'dark', 'pastel'], true)) {
            $values['home_contact_layout'] = 'clinic';
        }
        foreach (['highlight_items', 'why_items', 'faq_items', 'footer_features', 'home_about_features', 'home_about_stats', 'home_services_highlights'] as $key) {
            $values[$key] = array_values(array_filter($values[$key], fn ($item) => is_array($item) && is_string($item['title'] ?? null) && is_string($item['text'] ?? null)));
        }

        return $values;
    }
}
