<?php

namespace App\Http\Requests\Api\Internal\V1\Templates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateCollectionItemRequest extends FormRequest
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
            'templatable_type' => ['required', 'string', Rule::in(['workflow_template', 'agent_template'])],
            'templatable_id' => ['required', 'uuid'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
