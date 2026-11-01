<?php

namespace App\Services\Agents\Knowledge\Readers;

use App\Enums\Agents\KnowledgeSourceType;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\Knowledge\KnowledgeBatch;

/**
 * Reads one kind of knowledge source. App readers only run read-only nodes
 * (`NodeReader`), so syncing can never change anything in an app.
 */
interface KnowledgeReader
{
    public function type(): KnowledgeSourceType;

    /**
     * Validation rules for the source's `config.*`.
     *
     * @return array<string, mixed>
     */
    public function configRules(): array;

    public function read(KnowledgeSource $source, int $limit): KnowledgeBatch;
}
