<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\KnowledgeMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The collection may not have any chunks yet, so its name is held to the
     * same format `IngestKnowledgeRequest` creates collections with.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['collection' => $this->route('collection')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collection' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'mode' => ['sometimes', Rule::enum(KnowledgeMode::class)],
        ];
    }
}
