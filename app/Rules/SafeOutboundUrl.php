<?php

namespace App\Rules;

use App\Exceptions\Http\BlockedUrlException;
use App\Services\Http\GuardedHttp;
use App\Services\Http\SsrfGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects a URL the server must never fetch on a tenant's behalf (non-http(s)
 * schemes, embedded credentials, literal private/loopback/metadata addresses,
 * internal host names). Static checks only (no DNS lookup) so saving a form
 * never depends on the resolver; {@see GuardedHttp}
 * enforces the full check, DNS included, when the request is actually sent.
 */
class SafeOutboundUrl implements ValidationRule
{
    public function __construct(private readonly SsrfGuard $guard = new SsrfGuard) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        try {
            $this->guard->assertUrlSyntaxIsAllowed($value);
        } catch (BlockedUrlException) {
            $fail('The :attribute must be a public http(s) URL.');
        }
    }
}
