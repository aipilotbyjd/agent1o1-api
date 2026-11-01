<?php

namespace App\Services\Agents\Knowledge;

/**
 * What one sync read from a source.
 */
final readonly class KnowledgeBatch
{
    /**
     * @param  list<KnowledgeDocumentData>  $documents
     * @param  string|null  $cursor  where the next sync starts (null keeps the old one)
     * @param  bool  $complete  the batch is the whole source, so documents missing from it were deleted
     */
    public function __construct(
        public array $documents,
        public ?string $cursor = null,
        public bool $complete = false,
    ) {}
}
