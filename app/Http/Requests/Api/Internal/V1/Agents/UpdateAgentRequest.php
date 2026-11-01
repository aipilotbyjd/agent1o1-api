<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\AutonomyMode;
use App\Enums\Triggers\TriggerTargetType;
use App\Http\Requests\Api\Internal\V1\Workflows\Concerns\ValidatesWorkspaceFolder;
use App\Models\Agents\Agent;
use App\Rules\AllowedModel;
use App\Services\Agents\GenerationSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    use ValidatesWorkspaceFolder;

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
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'nullable', Rule::in(Agent::ICONS)],
            'color' => ['sometimes', 'nullable', Rule::in(Agent::COLORS)],
            'folder_id' => ['sometimes', 'nullable', 'uuid', $this->workspaceFolderExists(TriggerTargetType::Agent)],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'provider' => ['sometimes', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model_catalog_id' => ['sometimes', 'nullable', 'uuid', 'exists:model_catalog,id', new AllowedModel($this->route('workspace'))],
            'temperature' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'settings.max_steps' => ['nullable', 'integer', 'min:1', 'max:'.GenerationSettings::MAX_STEPS],
            'settings.max_tokens' => ['nullable', 'integer', 'min:1', 'max:'.GenerationSettings::MAX_TOKENS],
            'settings.top_p' => ['nullable', 'numeric', 'between:0,1'],
            'allow_self_updates' => ['sometimes', 'boolean'],
            'allow_skill_editing' => ['sometimes', 'boolean'],
            'allow_self_clone' => ['sometimes', 'boolean'],
            'autonomy_mode' => ['sometimes', Rule::enum(AutonomyMode::class)],
            'test_mode' => ['sometimes', 'boolean'],
            'allow_web_fetch' => ['sometimes', 'boolean'],
        ];
    }
}
