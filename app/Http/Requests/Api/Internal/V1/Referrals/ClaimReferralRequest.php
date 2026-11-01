<?php

namespace App\Http\Requests\Api\Internal\V1\Referrals;

use Illuminate\Foundation\Http\FormRequest;

class ClaimReferralRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64'],
            'visitor_id' => ['nullable', 'uuid'],
        ];
    }
}
