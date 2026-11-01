<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\KnowledgeMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentKnowledgeRequest extends FormRequest
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
            'mode' => ['required', Rule::enum(KnowledgeMode::class)],
        ];
    }
}
