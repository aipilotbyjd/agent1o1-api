<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Workflows\BuilderMessageMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowBuilderSessionRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'workflow_id' => ['nullable', 'uuid', 'exists:workflows,id'],
            'prompt' => ['nullable', 'string', 'max:10000'],
            // How the first message is sent: see `StoreWorkflowBuilderMessageRequest`.
            'mode' => ['nullable', Rule::enum(BuilderMessageMode::class)],
        ];
    }

    public function mode(): BuilderMessageMode
    {
        return BuilderMessageMode::tryFrom((string) $this->validated('mode')) ?? BuilderMessageMode::Build;
    }
}
