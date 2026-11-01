<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Rules\AllowedModel;
use Illuminate\Foundation\Http\FormRequest;

class DraftSkillRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:4000'],
            'model_catalog_id' => ['required', 'uuid', 'exists:model_catalog,id', new AllowedModel($this->route('workspace'))],
        ];
    }
}
