@props(['id', 'title', 'description' => '', 'status' => '', 'open' => false, 'create' => false])
<details id="{{ $id }}" class="ux-card ux-record" data-record-panel data-record-create="{{ $create ? 'true' : 'false' }}" data-record-search="{{ $title }} {{ $description }} {{ $status }}" @if($open || old('_ux_context') === $id) open @endif>
<summary><span><strong>{{ $title }}</strong>@if($description)<small>{{ $description }}</small>@endif<small class="text-warning" data-record-unsaved role="status" aria-live="polite"></small></span>@if($status)<span class="badge ux-status">{{ $status }}</span>@endif<span class="ux-record-action">بازکردن / ویرایش</span></summary>
<div class="ux-record-body">{{ $slot }}</div>
</details>
