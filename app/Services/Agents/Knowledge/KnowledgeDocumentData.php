<?php

namespace App\Services\Agents\Knowledge;

/**
 * One document as a reader found it, before it is chunked and stored.
 */
final readonly class KnowledgeDocumentData
{
    public function __construct(
        public string $externalId,
        public string $title,
        public string $text,
        public ?string $url = null,
    ) {}
}
