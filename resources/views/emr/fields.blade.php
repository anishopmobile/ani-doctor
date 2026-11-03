@php($jalali = app(App\Modules\Scheduling\Application\JalaliDate::class))
<div class="field-grid">
@foreach($fields as $key=>$definition)
@php($value = old('payload.'.$key, $values[$key] ?? ''))
@php($value = is_scalar($value) ? (string) $value : '')
@if($definition[2] === 'date' && !old('payload') && $value !== '')@php($value = $jalali->format(\Carbon\CarbonImmutable::parse($value, 'Asia/Tehran')))@endif
<label>{{ $definition[0] }}
@if($definition[2] === 'select')<select class="form-select" name="payload[{{ $key }}]" @disabled($readonly)>@foreach($definition[3] as $option=>$label)<option value="{{ $option }}" @selected($value === $option)>{{ $label }}</option>@endforeach</select>
@elseif($definition[2] === 'textarea')<textarea class="form-control" name="payload[{{ $key }}]" rows="3" @disabled($readonly)>{{ $value }}</textarea>
@else<input class="form-control" name="payload[{{ $key }}]" type="{{ $definition[2] === 'number' ? 'number' : 'text' }}" value="{{ $value }}" @if($definition[2] === 'date') data-persian-date @endif @disabled($readonly)>
@endif
@error('payload.'.$key)<span class="text-danger d-block">{{ $message }}</span>@enderror
</label>
@endforeach
</div>
