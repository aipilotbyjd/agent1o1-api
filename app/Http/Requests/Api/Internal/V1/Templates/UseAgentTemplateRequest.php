<?php

namespace App\Http\Requests\Api\Internal\V1\Templates;

use App\Rules\AllowedModel;
use Illuminate\Foundation\Http\FormRequest;

class UseAgentTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'model_catalog_id' => ['nullable', 'uuid', 'exists:model_catalog,id', new AllowedModel($this->route('workspace'))],
        ];
    }
}
