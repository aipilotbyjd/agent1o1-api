<?php

namespace App\Http\Requests\Api\Internal\V1\Artifacts;

use App\Models\Workspaces\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareArtifactRequest extends FormRequest
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
        $workspace = $this->route('workspace');
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : null;

        return [
            // Sharing is scoped to fellow workspace members: there's no
            // cross-workspace grant.
            'user_id' => [
                'required',
                'uuid',
                Rule::exists('workspace_members', 'user_id')->where('workspace_id', $workspaceId),
            ],
        ];
    }
}
