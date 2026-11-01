<?php

namespace App\Rules\SuperAdmin;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An IPv4/IPv6 address, or a CIDR range such as `203.0.113.0/24`.
 */
class IpOrCidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be an IP address or CIDR range.');

            return;
        }

        [$address, $mask] = array_pad(explode('/', $value, 2), 2, null);

        $isIpv6 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $isValidAddress = filter_var($address, FILTER_VALIDATE_IP) !== false;
        $maxMask = $isIpv6 ? 128 : 32;

        $isValidMask = $mask === null
            || (ctype_digit($mask) && (int) $mask >= 0 && (int) $mask <= $maxMask);

        if (! $isValidAddress || ! $isValidMask) {
            $fail('The :attribute must be an IP address or CIDR range.');
        }
    }
}
