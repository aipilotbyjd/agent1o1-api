<?php

namespace App\Http\Resources\Api\Internal\V1\KnowledgeBase;

use App\Models\Agents\DocumentEmbedding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentEmbedding
 */
class DocumentEmbeddingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection,
            'private' => $this->owner_id !== null,
            'knowledge_source_id' => $this->knowledge_source_id,
            'source' => $this->source,
            'chunk_text' => $this->chunk_text,
            // The vector itself is deliberately not serialized: it is
            // hundreds to thousands of floats per row and nothing outside
            // `KnowledgeBase`'s ranking has any use for it. Its size is
            // exposed instead, since a mismatch in dimensions is the usual
            // reason a chunk never ranks.
            'dimensions' => count($this->embedding ?? []),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
