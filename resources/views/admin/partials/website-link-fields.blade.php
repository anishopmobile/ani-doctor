@php($uiContext = 'link-'.($link?->id ?? 'create'))
@php($uiOld = fn ($field, $fallback = null) => \App\Support\UiInput::scalar($field, $fallback, $uiContext))
<div class="field-grid">
    <label>محل نمایش
        <select class="form-select" name="placement" required>
            <option value="header" @selected($uiOld('placement', $link?->placement) === 'header')>هدر</option>
            <option value="footer" @selected($uiOld('placement', $link?->placement) === 'footer')>فوتر</option>
        </select>
    </label>
    <label>عنوان لینک
        <input class="form-control" name="label" required maxlength="100" value="{{ $uiOld('label', $link?->label) }}">
    </label>
    <label>نشانی لینک (مانند /calendar یا #services یا https://example.ir)
        <input class="form-control" name="url" required maxlength="500" dir="ltr" value="{{ $uiOld('url', $link?->url) }}">
    </label>
    <label>ترتیب
        <input class="form-control" type="number" name="sort_order" min="0" max="100000" required value="{{ $uiOld('sort_order', $link?->sort_order ?? 0) }}">
    </label>
    <label>وضعیت
        <select class="form-select" name="is_published">
            <option value="0" @selected((string) $uiOld('is_published', (int) ($link?->is_published ?? false)) === '0')>پیش‌نویس</option>
            <option value="1" @selected((string) $uiOld('is_published', (int) ($link?->is_published ?? false)) === '1')>منتشرشده</option>
        </select>
    </label>
    <label>باز شدن لینک
        <select class="form-select" name="new_tab">
            <option value="0" @selected((string) $uiOld('new_tab', (int) ($link?->new_tab ?? false)) === '0')>همین تب</option>
            <option value="1" @selected((string) $uiOld('new_tab', (int) ($link?->new_tab ?? false)) === '1')>تب جدید</option>
        </select>
    </label>
</div>
