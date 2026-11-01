<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Rules\AllowedModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssistantRequest extends FormRequest
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
            'instructions' => ['sometimes', 'nullable', 'string', 'max:'.config('assistant.limits.instructions_max_chars')],
            'model_catalog_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('model_catalog', 'id'), new AllowedModel($this->route('workspace'))],
        ];
    }
}
