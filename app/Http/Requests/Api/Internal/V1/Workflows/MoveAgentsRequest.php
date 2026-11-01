<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Triggers\TriggerTargetType;
use App\Http\Requests\Api\Internal\V1\Workflows\Concerns\ValidatesWorkspaceFolder;
use Illuminate\Foundation\Http\FormRequest;

class MoveAgentsRequest extends FormRequest
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
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => ['uuid'],
            'folder_id' => ['nullable', 'uuid', $this->workspaceFolderExists(TriggerTargetType::Agent)],
        ];
    }
}
