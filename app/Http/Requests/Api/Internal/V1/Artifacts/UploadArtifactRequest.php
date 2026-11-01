<?php

namespace App\Http\Requests\Api\Internal\V1\Artifacts;

use App\Models\Workspaces\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadArtifactRequest extends FormRequest
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
        $workspaceId = $this->workspace()?->id;

        return [
            'file' => ['required', 'file', 'max:'.config('artifacts.max_upload_kilobytes')],
            // Defaults to the uploaded file's own name; supplied explicitly
            // when the client wants to rename on the way in.
            'filename' => ['nullable', 'string', 'max:255'],
            // Optional: files an agent should be able to work with are filed
            // under it. Scoped to this workspace so an upload can't be
            // attributed to another tenant's agent.
            'agent_id' => [
                'nullable',
                'uuid',
                Rule::exists('agents', 'id')->where('workspace_id', $workspaceId)->whereNull('deleted_at'),
            ],
            // Supplied to add a version to an existing artifact group rather
            // than matching on filename.
            'group_id' => [
                'nullable',
                'uuid',
                Rule::exists('artifacts', 'group_id')->where('workspace_id', $workspaceId),
            ],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function workspace(): ?Workspace
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace ? $workspace : null;
    }
}
