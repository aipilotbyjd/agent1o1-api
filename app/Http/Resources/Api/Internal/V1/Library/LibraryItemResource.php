<?php

namespace App\Http\Resources\Api\Internal\V1\Library;

use App\Enums\Agents\AgentMessageRole;
use App\Models\Artifacts\Artifact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Artifact
 */
class LibraryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'kind' => str_starts_with($this->mime_type, 'image/') ? 'image' : 'file',
            'size' => $this->size,
            // A file attached to a member's message was uploaded; anything
            // else in a chat was made by the agent.
            'source' => $this->workflow_builder_message_id !== null || $this->agentMessage?->role === AgentMessageRole::User ? 'uploaded' : 'generated',
            'agent' => $this->agent === null ? null : [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'icon' => $this->agent->icon,
                'color' => $this->agent->color,
            ],
            'chat' => $this->agentSession === null ? null : [
                'id' => $this->agentSession->id,
                'title' => $this->agentSession->title,
            ],
            'view_url' => $this->isPreviewable()
                ? URL::temporarySignedRoute('library.view', now()->addMinutes(30), ['artifact' => $this->id])
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
