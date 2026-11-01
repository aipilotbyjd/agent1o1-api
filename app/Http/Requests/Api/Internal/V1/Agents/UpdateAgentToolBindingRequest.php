<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Http\Requests\Api\Internal\V1\Agents\Concerns\ValidatesApprovalRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentToolBindingRequest extends FormRequest
{
    use ValidatesApprovalRules;

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
            'config' => ['sometimes', 'nullable', 'array'],
            'exposed_fields' => ['sometimes', 'nullable', 'array'],
            'exposed_fields.*' => ['string'],
            ...$this->approvalPolicyRules(),
        ];
    }
}
