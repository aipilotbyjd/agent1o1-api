<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentMemoryRequest extends FormRequest
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
            // Memory can only be kept about a member of this workspace.
            'user_id' => ['nullable', 'uuid', Rule::exists('workspace_members', 'user_id')->where('workspace_id', $this->route('workspace')?->id)],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:30'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
