<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Http\Requests\Api\Internal\V1\Agents\Concerns\ValidatesApprovalRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentToolBindingRequest extends FormRequest
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
            'node_type' => [
                'required', 'string', 'max:255',
                Rule::unique('agent_node', 'node_type')->where('agent_id', $this->route('agent')->id),
            ],
            'config' => ['nullable', 'array'],
            'exposed_fields' => ['nullable', 'array'],
            'exposed_fields.*' => ['string'],
            ...$this->approvalPolicyRules(),
        ];
    }
}
