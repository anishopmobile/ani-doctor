@php($uiContext = 'article-'.($article?->id ?? 'create'))
@php($uiOld = fn ($field, $fallback = null) => \App\Support\UiInput::scalar($field, $fallback, $uiContext))
<div class="field-grid">
    <label>عنوان مقاله
        <input class="form-control" name="title" maxlength="180" required value="{{ $uiOld('title', $article?->title) }}">
    </label>
    <label>نام نویسنده (اختیاری و قابل نمایش عمومی)
        <input class="form-control" name="author_name" maxlength="120" value="{{ $uiOld('author_name', $article?->author_name) }}">
    </label>
    <label>شناسه انگلیسی URL (مثال: prenatal-care)
        <input class="form-control" name="slug" maxlength="120" required dir="ltr" value="{{ $uiOld('slug', $article?->slug) }}">
    </label>
    <label>توضیح متا (حداکثر ۱۶۰ نویسه)
        <input class="form-control" name="meta_description" maxlength="160" value="{{ $uiOld('meta_description', $article?->meta_description) }}">
    </label>
    <label>انتشار
        <select class="form-select" name="is_published">
            <option value="0" @selected((string) $uiOld('is_published', (int) ($article?->is_published ?? false)) === '0')>پیش‌نویس</option>
            <option value="1" @selected((string) $uiOld('is_published', (int) ($article?->is_published ?? false)) === '1')>آماده انتشار</option>
        </select>
    </label>
    <label>تاریخ انتشار شمسی، به وقت تهران
        <input class="form-control" name="published_jalali" data-persian-date placeholder="انتخاب تاریخ شمسی" dir="ltr" maxlength="10" value="{{ $uiOld('published_jalali', $article?->published_at ? app(App\Modules\Scheduling\Application\JalaliDate::class)->format($article->published_at) : '') }}">
    </label>
    <label class="d-block mb-3">ساعت انتشار به وقت تهران<input class="form-control" type="time" name="published_time" value="{{ $uiOld('published_time', $article?->published_at?->timezone('Asia/Tehran')->format('H:i') ?? '00:00') }}"></label>
</div>
<label class="d-block mt-2">خلاصه
    <textarea class="form-control" name="summary" rows="2" maxlength="350">{{ $uiOld('summary', $article?->summary) }}</textarea>
</label>
<label class="d-block mt-2">متن مقاله (متن ساده؛ HTML اجرا نمی‌شود)
    <textarea class="form-control" name="body" rows="8" required maxlength="50000">{{ $uiOld('body', $article?->body) }}</textarea>
</label>

<fieldset class="border rounded p-3 mt-3">
    <legend class="float-none w-auto fs-6 px-2">دسته‌بندی‌ها و برچسب‌ها</legend>
    @foreach($taxonomies as $taxonomy)
        <label class="d-inline-flex gap-2 align-items-center me-3 mb-2">
            <input type="checkbox" name="taxonomy_ids[]" value="{{ $taxonomy->id }}" @checked(in_array($taxonomy->id, \App\Support\UiInput::choices('taxonomy_ids', $article?->taxonomies?->pluck('id')->all() ?? [], $uiContext)))>
            {{ $taxonomy->name }} ({{ $taxonomy->type === 'tag' ? 'برچسب' : 'دسته' }})
        </label>
    @endforeach
</fieldset>
