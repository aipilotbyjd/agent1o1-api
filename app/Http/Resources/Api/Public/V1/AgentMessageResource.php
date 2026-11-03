<?php

namespace App\Http\Resources\Api\Public\V1;

use App\Models\Agents\AgentMessage;
use App\Services\Agents\KnowledgeCitations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentMessage
 */
class AgentMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            // Citation markers become numbers that index `citations`.
            'content' => app(KnowledgeCitations::class)->plain((string) $this->content, $this->citations),
            'citations' => $this->publicCitations(),
            // While true, the reply is on hold until its waiting actions are decided (`/actions`).
            'awaiting_approval' => $this->paused_state !== null,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return list<array{number: int, source: string|null, collection: string|null, url: string|null, snippet: string}>
     */
    private function publicCitations(): array
    {
        return collect($this->citations ?? [])
            ->map(fn (array $citation): array => collect($citation)->only(['number', 'source', 'collection', 'url', 'snippet'])->all())
            ->values()
            ->all();
    }
}
