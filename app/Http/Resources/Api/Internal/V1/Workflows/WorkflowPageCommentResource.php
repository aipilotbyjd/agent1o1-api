<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowPageComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment thread (or a reply in one) with who wrote it and whether the
 * person reading may change it.
 *
 * @mixin WorkflowPageComment
 */
class WorkflowPageCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = fn ($user): ?array => $user === null ? null : ['id' => $user->id, 'name' => $user->name];

        return [
            'id' => $this->id,
            'block_id' => $this->block_id,
            'body' => $this->body,
            'author' => $person($this->author),
            'is_mine' => $this->user_id === $request->user()?->id,
            'resolved_at' => $this->resolved_at,
            'resolved_by' => $this->whenLoaded('resolver', fn () => $person($this->resolver)),
            'replies' => self::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
