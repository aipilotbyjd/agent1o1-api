<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Triggers\TriggerTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
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
            'type' => ['required', Rule::enum(TriggerTargetType::class)],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('folders', 'id')
                    ->where('workspace_id', $this->route('workspace')?->id)
                    ->where('type', $this->input('type')),
            ],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
