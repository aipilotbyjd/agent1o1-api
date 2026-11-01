<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Billing;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

/**
 * Adds or takes back top-up credits (the same shape for both).
 */
class GrantCreditsRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'credits' => ['required', 'integer', 'min:1', 'max:'.config('super_admin_security.limits.credit_grant_max')],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
