@props(['status'])
@php
    $states = ['pending_payment' => ['رزرو موقت، در انتظار پرداخت', 'warning'], 'confirmed' => ['قطعی', 'success'], 'expired' => ['منقضی', 'danger'], 'cancelled' => ['لغوشده', 'danger'], 'completed' => ['انجام‌شده', 'success'], 'no_show' => ['عدم مراجعه', 'warning']];
    [$label, $variant] = $states[$status] ?? ['در حال بررسی', 'info'];
@endphp
<span class="status-badge status-{{ $variant }}">{{ $label }}</span>
