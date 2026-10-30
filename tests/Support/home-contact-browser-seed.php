<?php

require __DIR__.'/panel-browser-seed.php';

use App\Modules\Website\Application\ThemeService;
use App\Modules\Website\Infrastructure\WebsiteLink;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use Illuminate\Support\Facades\Storage;

$path = 'website-media/home-contact-ui.webp';
Storage::disk('local')->put($path, file_get_contents(__DIR__.'/../../public/assets/clinic-art.webp'));
WebsiteMedia::updateOrCreate(['owner_type' => 'contact', 'owner_id' => 1], ['path' => $path, 'mime_type' => 'image/webp', 'alt_text' => 'تصویر مستقل ساختگی آزمون تماس']);
WebsiteLink::updateOrCreate(['placement' => 'social', 'label' => 'واتساپ ساختگی آزمون'], ['url' => 'https://wa.me/989000000000', 'is_published' => true]);
app(ThemeService::class)->save([
    'home_contact_layout' => 'clinic', 'show_contact' => true, 'home_contact_form_enabled' => true, 'contact_is_published' => true,
    'contact_title' => 'عنوان ساختگی آزمون سه مدل تماس', 'home_contact_badge' => 'نشان ساختگی برای آزمون رابط',
    'contact_hours' => 'ساعات پاسخ‌گویی ساختگی آزمون', 'contact_email' => 'synthetic@example.test',
    'contact_secondary_phone' => '02100000000', 'contact_map_url' => 'https://maps.google.com/?q=synthetic-test',
]);
echo "HOME_CONTACT_SYNTHETIC_FIXTURE_READY\n";
