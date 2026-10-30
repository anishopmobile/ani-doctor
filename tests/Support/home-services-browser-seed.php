<?php

// Refuse any database other than the isolated synthetic browser fixture.
require __DIR__.'/panel-browser-seed.php';

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Application\ServiceContentService;
use App\Modules\Website\Application\ThemeService;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use Illuminate\Support\Facades\Storage;

for ($i = 1; $i <= 5; $i++) {
    Service::firstOrCreate(['name' => 'خدمت ساختگی آزمون مدل خدمات '.$i], ['price_irr' => 1000000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
}
$services = Service::where('is_active', true)->where('is_online', true)->orderBy('id')->get();
foreach ($services as $index => $service) {
    app(ServiceContentService::class)->save($service->id, ['summary' => $index === 1 ? 'PRIVATE-SERVICE-DRAFT' : 'توضیح ذخیره‌شده ساختگی برای بررسی خوانایی خدمت در صفحه اصلی', 'is_published' => $index !== 1, 'sort_order' => $index]);
}
foreach (['services' => 1, 'service' => $services->first()->id] as $type => $owner) {
    $path = 'website-media/home-services-'.$type.'-ui.webp';
    Storage::disk('local')->put($path, file_get_contents(__DIR__.'/../../public/assets/clinic-art.webp'));
    WebsiteMedia::updateOrCreate(['owner_type' => $type, 'owner_id' => $owner], ['path' => $path, 'mime_type' => 'image/webp', 'alt_text' => 'تصویر ساختگی آزمون خدمات']);
}
app(ThemeService::class)->save([
    'home_services_layout' => 'light', 'show_services' => true, 'services_is_published' => true,
    'services_title' => 'عنوان ساختگی آزمون مدل‌های خدمات',
    'home_services_badge' => 'نشان ساختگی آزمون',
    'home_services_highlights' => [['title' => 'نکته ساختگی آزمون', 'text' => 'متن ذخیره‌شده فقط برای آزمون']],
]);
echo "HOME_SERVICES_SYNTHETIC_FIXTURE_READY\n";
