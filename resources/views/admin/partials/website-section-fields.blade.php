<div class="field-grid">
    <label>نوع سکشن
        <select class="form-select" name="type" required>
            @foreach(['hero' => 'معرفی اصلی', 'about' => 'درباره پزشک', 'cta' => 'دعوت به اقدام', 'slide' => 'اسلاید صفحه اصلی'] as $key => $name)
                <option value="{{ $key }}" @selected(old('type', $section?->type) === $key)>{{ $name }}</option>
            @endforeach
        </select>
    </label>
    <label>عنوان
        <input class="form-control" name="title" required maxlength="180" value="{{ old('title', $section?->title) }}">
    </label>
    <label>زیرعنوان
        <input class="form-control" name="subtitle" maxlength="240" value="{{ old('subtitle', $section?->subtitle) }}">
    </label>
    <label>متن دکمه
        <input class="form-control" name="button_text" maxlength="80" value="{{ old('button_text', $section?->button_text) }}">
    </label>
    <label>لینک داخلی دکمه (مانند /calendar یا #contact)
        <input class="form-control" name="button_url" maxlength="255" value="{{ old('button_url', $section?->button_url) }}" dir="ltr">
    </label>
    <label>پس‌زمینه
        <select class="form-select" name="background">
            @foreach(['plain' => 'سفید', 'blush' => 'صورتی', 'lilac' => 'یاسی'] as $key => $name)
                <option value="{{ $key }}" @selected(old('background', $section?->background ?? 'plain') === $key)>{{ $name }}</option>
            @endforeach
        </select>
    </label>
    <label>ترتیب
        <input class="form-control" type="number" min="0" max="100000" name="sort_order" value="{{ old('sort_order', $section?->sort_order ?? 0) }}" required>
    </label>
    <label>وضعیت
        <select class="form-select" name="is_published">
            <option value="0" @selected((string) old('is_published', (int) ($section?->is_published ?? false)) === '0')>پیش‌نویس</option>
            <option value="1" @selected((string) old('is_published', (int) ($section?->is_published ?? false)) === '1')>منتشرشده</option>
        </select>
    </label>
</div>
<label class="d-block mt-2">توضیحات
    <textarea class="form-control" name="body" rows="4" maxlength="3000">{{ old('body', $section?->body) }}</textarea>
</label>
