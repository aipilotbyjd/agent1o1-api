<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejecting a referral and revoking a reward both need a reason on record.
 */
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
