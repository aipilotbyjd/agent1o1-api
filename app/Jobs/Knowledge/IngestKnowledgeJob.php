<?php

namespace App\Jobs\Knowledge;

use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Chunks, embeds and stores a large document off the request: embedding is
 * one provider call per batch of chunks, which for a multi-megabyte upload
 * would outlast an HTTP request. Replaces any earlier ingest of the same
 * `source` in the collection, so a retry (or a re-upload) never duplicates.
 * Dispatched by `KnowledgeBaseController::store()` above
 * `knowledge_base.sync_ingest_max_characters`.
 */
class IngestKnowledgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public Workspace $workspace,
        public string $text,
        public ?string $source,
        public string $collection,
        public ?array $metadata = null,
        public ?string $ownerId = null,
        public ?int $revision = null,
    ) {}

    public function handle(KnowledgeBase $knowledgeBase): void
    {
        $knowledgeBase->ingest(
            $this->workspace,
            $this->text,
            $this->source,
            $this->collection,
            $this->metadata,
            ownerId: $this->ownerId,
            replaceSource: true,
            revision: $this->revision,
        );
    }
}
