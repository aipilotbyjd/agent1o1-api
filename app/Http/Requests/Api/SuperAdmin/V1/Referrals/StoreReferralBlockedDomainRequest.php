<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralBlockedDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            $this->merge(['domain' => mb_strtolower(trim($this->input('domain')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Shared with the `referrals:domain` command. The domain must already be
     * lower-cased and trimmed.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255', 'regex:/^([a-z0-9-]+\.)+[a-z]{2,}$/', Rule::unique('referral_blocked_domains', 'domain')],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
