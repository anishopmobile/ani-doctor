<div class="field-grid">
    <label>عنوان مقاله
        <input class="form-control" name="title" maxlength="180" required value="{{ old('title', $article?->title) }}">
    </label>
    <label>شناسه انگلیسی URL (مثال: prenatal-care)
        <input class="form-control" name="slug" maxlength="120" required dir="ltr" value="{{ old('slug', $article?->slug) }}">
    </label>
    <label>توضیح متا (حداکثر ۱۶۰ نویسه)
        <input class="form-control" name="meta_description" maxlength="160" value="{{ old('meta_description', $article?->meta_description) }}">
    </label>
    <label>انتشار
        <select class="form-select" name="is_published">
            <option value="0" @selected((string) old('is_published', (int) ($article?->is_published ?? false)) === '0')>پیش‌نویس</option>
            <option value="1" @selected((string) old('is_published', (int) ($article?->is_published ?? false)) === '1')>آماده انتشار</option>
        </select>
    </label>
    <label>زمان انتشار (YYYY-MM-DD HH:MM:SS)
        <input class="form-control" name="published_at" placeholder="2026-10-27 15:00:00" dir="ltr" value="{{ old('published_at', $article?->published_at?->format('Y-m-d H:i:s')) }}">
    </label>
</div>
<label class="d-block mt-2">خلاصه
    <textarea class="form-control" name="summary" rows="2" maxlength="350">{{ old('summary', $article?->summary) }}</textarea>
</label>
<label class="d-block mt-2">متن مقاله (متن ساده؛ HTML اجرا نمی‌شود)
    <textarea class="form-control" name="body" rows="8" required maxlength="50000">{{ old('body', $article?->body) }}</textarea>
</label>

<fieldset class="border rounded p-3 mt-3">
    <legend class="float-none w-auto fs-6 px-2">دسته‌بندی‌ها و برچسب‌ها</legend>
    @foreach($taxonomies as $taxonomy)
        <label class="d-inline-flex gap-2 align-items-center me-3 mb-2">
            <input type="checkbox" name="taxonomy_ids[]" value="{{ $taxonomy->id }}" @checked(in_array($taxonomy->id, old('taxonomy_ids', $article?->taxonomies?->pluck('id')->all() ?? [])))>
            {{ $taxonomy->name }} ({{ $taxonomy->type === 'tag' ? 'برچسب' : 'دسته' }})
        </label>
    @endforeach
</fieldset>
