<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Workflows\BuilderMessageMode;
use App\Models\Workspaces\Workspace;
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
            'workflow_id' => [
                'nullable',
                'uuid',
                Rule::exists('workflows', 'id')
                    ->where('workspace_id', $this->workspace()->id)
                    ->whereNull('deleted_at'),
            ],
            'prompt' => ['nullable', 'string', 'max:10000'],
            // How the first message is sent: see `StoreWorkflowBuilderMessageRequest`.
            'mode' => ['nullable', Rule::enum(BuilderMessageMode::class)],
        ];
    }

    private function workspace(): Workspace
    {
        return $this->route('workspace');
    }

    public function mode(): BuilderMessageMode
    {
        return BuilderMessageMode::tryFrom((string) $this->validated('mode')) ?? BuilderMessageMode::Build;
    }
}
