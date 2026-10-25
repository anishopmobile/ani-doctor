<?php

namespace App\Modules\Identity\Domain;

final class MobileNumber
{
    public static function normalize(string $value): string
    {
        return strtr(trim($value), array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789'),
        ));
    }

    public static function isValid(string $value): bool
    {
        return preg_match('/\A09[0-9]{9}\z/D', $value) === 1;
    }
}
