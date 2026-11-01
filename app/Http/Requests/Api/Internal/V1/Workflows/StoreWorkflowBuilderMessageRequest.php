<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workflows\BuilderMessageMode;
use App\Http\Requests\Concerns\ValidatesMessageAttachments;
use App\Models\Workspaces\Workspace;
use App\Rules\AllowedModel;
use App\Services\Workflows\NodeRegistry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowBuilderMessageRequest extends FormRequest
{
    use ValidatesMessageAttachments;

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
            'message' => ['required', 'string', 'max:10000'],
            // `ask` answers without editing the draft. Defaults to `build`.
            'mode' => ['nullable', Rule::enum(BuilderMessageMode::class)],
            // The model to run this turn on, from the public catalog. Left out, the assistant runs on its own default.
            'model_catalog_id' => ['nullable', 'uuid', Rule::exists('model_catalog', 'id')->where('is_active', 1)->where('is_internal', 0), new AllowedModel($this->workspace())],
            // Sent as multipart `attachments[]`. Each file is stored as an
            // artifact on the new message and handed to the model with it.
            // No PDFs: the builder's model routes only take images.
            ...$this->attachmentRules(documents: false),
            // What the member picked in the prompt with `/` (steps) and `@`
            // (connected accounts), so the builder gets ids rather than names.
            'context' => ['nullable', 'array:nodes,credentials'],
            'context.nodes' => ['array', 'max:20'],
            'context.nodes.*' => ['string', 'distinct', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(NodeRegistry::class)->isPlaceable($value)) {
                    $fail('That step type is not available to the builder.');
                }
            }],
            'context.credentials' => ['array', 'max:20'],
            // Only accounts the sender may use: the workspace's team ones and their own personal ones.
            'context.credentials.*' => ['uuid', 'distinct', Rule::exists('connector_credentials', 'id')
                ->where('workspace_id', $this->workspace()->id)
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query
                    ->where('scope', ConnectorCredentialScope::Team->value)
                    ->orWhere('created_by', $this->user()->id))],
        ];
    }

    /**
     * @return array{nodes?: list<string>, credentials?: list<string>}|null
     */
    public function context(): ?array
    {
        $context = array_filter([
            'nodes' => array_values($this->validated('context.nodes', [])),
            'credentials' => array_values($this->validated('context.credentials', [])),
        ]);

        return $context === [] ? null : $context;
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
