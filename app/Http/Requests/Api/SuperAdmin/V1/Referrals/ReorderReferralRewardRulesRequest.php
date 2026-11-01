<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use Illuminate\Foundation\Http\FormRequest;

class ReorderReferralRewardRulesRequest extends FormRequest
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
            'rule_ids' => ['required', 'array', 'min:1'],
            'rule_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
