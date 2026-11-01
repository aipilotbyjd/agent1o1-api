<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PostmarkListMessageStreamsNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_list_message_streams';
    }

    public function name(): string
    {
        return 'Postmark: List Message Streams';
    }

    public function description(): string
    {
        return 'Lists the server\'s message streams (transactional, broadcast, inbound).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = (array) $this->postmark($run, $config, 'GET', '/message-streams');
        $streams = array_map(fn (array $stream): array => ['id' => $stream['ID'] ?? null, 'name' => $stream['Name'] ?? null, 'type' => $stream['MessageStreamType'] ?? null, 'archived' => $stream['ArchivedAt'] ?? null], array_values(array_filter((array) ($found['MessageStreams'] ?? []), 'is_array')));

        return ['streams' => $streams, 'count' => count($streams)];
    }
}
