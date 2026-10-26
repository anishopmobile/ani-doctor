<?php

namespace App\Modules\Operations\Domain;

final class ProxyAddresses
{
    public static function valid(array $proxies): bool
    {
        foreach ($proxies as $proxy) {
            if (!is_string($proxy)) {
                return false;
            }
            $parts = explode('/', $proxy, 2);
            $ipv4 = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            if (!filter_var($parts[0], FILTER_VALIDATE_IP) || (isset($parts[1]) && (!ctype_digit($parts[1]) || (int) $parts[1] < 1 || (int) $parts[1] > ($ipv4 ? 32 : 128)))) {
                return false;
            }
        }
        return true;
    }
}
