<?php

namespace App\Http\Requests\Api\Internal\V1\Referrals;

use Illuminate\Foundation\Http\FormRequest;

class RecordReferralVisitRequest extends FormRequest
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
            'landing_url' => ['nullable', 'string', 'max:2048'],
            'referrer_url' => ['nullable', 'string', 'max:2048'],
            'utm' => ['nullable', 'array:source,medium,campaign,term,content'],
            'utm.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
