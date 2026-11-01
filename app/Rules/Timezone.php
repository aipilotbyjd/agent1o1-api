<?php

namespace App\Rules;

use App\Services\Timezones\Timezones;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A timezone from `Timezones`: a current IANA name, or a retired one a
 * browser still reports. Store it through `Timezones::canonical()`.
 */
class Timezone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Timezones::canonical($value) === null) {
            $fail('Pick a timezone from the list.');
        }
    }
}
