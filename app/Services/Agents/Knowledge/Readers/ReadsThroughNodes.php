<?php

namespace App\Services\Agents\Knowledge\Readers;

use App\Models\Agents\KnowledgeSource;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Assistant\Briefings\NodeReader;
use RuntimeException;

/**
 * For readers of connected apps: the source's pinned account, and nodes run
 * read-only as the member who added the source.
 */
trait ReadsThroughNodes
{
    public function __construct(protected readonly NodeReader $reader) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function node(KnowledgeSource $source, string $type, array $config = []): array
    {
        return $this->reader->readAs($type, $source->workspace, $source->created_by, $this->credential($source), $config);
    }

    protected function credential(KnowledgeSource $source): ConnectorCredential
    {
        return $source->credential ?? throw new RuntimeException('The connected account for this source was removed. Reconnect it in Apps.');
    }
}
