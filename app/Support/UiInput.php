<?php

namespace App\Support;

/** Presentation-only restoration for pages containing several independent forms. */
final class UiInput
{
    public static function scalar(string $field, mixed $fallback, string $context): mixed
    {
        if (old('_ux_context') !== null && old('_ux_context') !== $context) {
            return $fallback;
        }
        $value = old($field, $fallback);
        return is_scalar($value) || $value === null ? $value : $fallback;
    }

    public static function choices(string $field, array $fallback, string $context): array
    {
        if (old('_ux_context') !== null && old('_ux_context') !== $context) {
            return $fallback;
        }
        $value = old($field, $fallback);
        return is_array($value) ? array_filter($value, fn ($item) => is_string($item) || is_int($item)) : $fallback;
    }
}
