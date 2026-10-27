<div class="field-grid">
    <label>محل نمایش
        <select class="form-select" name="placement" required>
            <option value="header" @selected(old('placement', $link?->placement) === 'header')>هدر</option>
            <option value="footer" @selected(old('placement', $link?->placement) === 'footer')>فوتر</option>
        </select>
    </label>
    <label>عنوان لینک
        <input class="form-control" name="label" required maxlength="100" value="{{ old('label', $link?->label) }}">
    </label>
    <label>نشانی لینک (مانند /calendar یا #services یا https://example.ir)
        <input class="form-control" name="url" required maxlength="500" dir="ltr" value="{{ old('url', $link?->url) }}">
    </label>
    <label>ترتیب
        <input class="form-control" type="number" name="sort_order" min="0" max="100000" required value="{{ old('sort_order', $link?->sort_order ?? 0) }}">
    </label>
    <label>وضعیت
        <select class="form-select" name="is_published">
            <option value="0" @selected((string) old('is_published', (int) ($link?->is_published ?? false)) === '0')>پیش‌نویس</option>
            <option value="1" @selected((string) old('is_published', (int) ($link?->is_published ?? false)) === '1')>منتشرشده</option>
        </select>
    </label>
    <label>باز شدن لینک
        <select class="form-select" name="new_tab">
            <option value="0" @selected((string) old('new_tab', (int) ($link?->new_tab ?? false)) === '0')>همین تب</option>
            <option value="1" @selected((string) old('new_tab', (int) ($link?->new_tab ?? false)) === '1')>تب جدید</option>
        </select>
    </label>
</div>
