<?php

// Existing guard restricts this seed to an isolated synthetic SQLite fixture.
require __DIR__.'/panel-browser-seed.php';

$category = App\Modules\Website\Infrastructure\WebsiteTaxonomy::firstOrCreate(['type' => 'category', 'slug' => 'ui-care'], ['name' => 'مراقبت ساختگی آزمون']);
$otherCategory = App\Modules\Website\Infrastructure\WebsiteTaxonomy::firstOrCreate(['type' => 'category', 'slug' => 'ui-other'], ['name' => 'دسته دوم ساختگی']);
foreach (range(1, 17) as $index) {
    $article = App\Modules\Website\Infrastructure\WebsiteArticle::updateOrCreate(['slug' => 'ui-article-'.$index], [
        'title' => 'مقاله ساختگی '.$index.'؛ آگاهی و مراقبت در مسیر سلامتی',
        'summary' => 'این خلاصه ساختگی فقط برای آزمون خوانایی کارت‌ها و نمایش اطلاعات ذخیره‌شده در سامانه است.',
        'body' => str_repeat("متن ساختگی آزمون مقاله؛ محتوای پزشکی واقعی نیست. این متن برای بررسی خوانایی سطرها در نمایشگر کوچک و بزرگ است.\n\n", 7),
        'author_name' => 'نویسنده ساختگی آزمون', 'is_published' => true, 'published_at' => now()->subDays($index),
    ]);
    $article->taxonomies()->sync([$index <= 15 ? $category->id : $otherCategory->id]);
    if ($index === 1) {
        $path = 'website-media/ui-article-image.webp';
        Illuminate\Support\Facades\Storage::disk('local')->put($path, file_get_contents(__DIR__.'/../../public/assets/clinic-art.webp'));
        App\Modules\Website\Infrastructure\WebsiteMedia::updateOrCreate(['owner_type' => 'article', 'owner_id' => $article->id], ['path' => $path, 'mime_type' => 'image/webp', 'alt_text' => 'تصویر ساختگی آزمون']);
    }
}
echo "ARTICLE_SYNTHETIC_FIXTURE_READY\n";
