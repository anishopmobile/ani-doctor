@props(['service' => null])
<div class="field-grid">
    <label>نام خدمت<input class="form-control" name="name" value="{{ $service?->name ?? old('name') }}" maxlength="150" required></label>
    <label>تعرفه (ریال)<input class="form-control" name="price_irr" inputmode="numeric" value="{{ $service?->price_irr ?? old('price_irr',0) }}" required><span class="form-text">مبلغ کامل خدمت؛ عدد صحیح به ریال</span></label>
    <label>مدت (دقیقه)<input class="form-control" type="number" name="duration_minutes" min="5" max="480" value="{{ $service?->duration_minutes ?? old('duration_minutes',30) }}" required></label>
    <label>فاصله پس از خدمت (دقیقه)<input class="form-control" type="number" name="buffer_minutes" min="0" max="240" value="{{ $service?->buffer_minutes ?? old('buffer_minutes',0) }}" required></label>
    <label>ظرفیت خدمت<input class="form-control" type="number" name="capacity" min="1" max="20" value="{{ $service?->capacity ?? old('capacity',1) }}" required></label>
</div>
<input type="hidden" name="is_active" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($service?->is_active ?? true)> فعال</label>
<input type="hidden" name="is_online" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_online" value="1" @checked($service?->is_online ?? true)> قابل نمایش در تقویم اینترنتی</label>
