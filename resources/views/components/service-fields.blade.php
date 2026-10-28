@props(['service' => null])
@php($uiContext = 'service-'.($service?->id ?? 'create'))
@php($uiOld = fn ($field, $fallback = null) => \App\Support\UiInput::scalar($field, $fallback, $uiContext))
<div class="field-grid">
    <label>نام خدمت<input class="form-control" name="name" value="{{ $uiOld('name', $service?->name ?? '') }}" maxlength="150" required></label>
    <label>تعرفه (ریال)<input class="form-control" name="price_irr" inputmode="numeric" value="{{ $uiOld('price_irr', $service?->price_irr ?? 0) }}" required><span class="form-text">مبلغ کامل خدمت؛ عدد صحیح به ریال</span></label>
    <label>مدت (دقیقه)<input class="form-control" type="number" name="duration_minutes" min="5" max="480" value="{{ $uiOld('duration_minutes', $service?->duration_minutes ?? 30) }}" required></label>
    <label>فاصله پس از خدمت (دقیقه)<input class="form-control" type="number" name="buffer_minutes" min="0" max="240" value="{{ $uiOld('buffer_minutes', $service?->buffer_minutes ?? 0) }}" required></label>
    <label>ظرفیت خدمت<input class="form-control" type="number" name="capacity" min="1" max="20" value="{{ $uiOld('capacity', $service?->capacity ?? 1) }}" required></label>
</div>
<input type="hidden" name="is_active" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($uiOld('is_active', $service?->is_active ?? true))> فعال</label>
<input type="hidden" name="is_online" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_online" value="1" @checked($uiOld('is_online', $service?->is_online ?? true))> قابل نمایش در تقویم اینترنتی</label>
