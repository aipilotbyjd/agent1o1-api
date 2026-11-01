<?php

namespace App\Nodes\Integrations\ZohoCliq;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCliqListChannelsNode extends AbstractZohoCliqNode
{
    public function type(): string
    {
        return 'zoho_cliq_list_channels';
    }

    public function name(): string
    {
        return 'Zoho Cliq: List Channels';
    }

    public function description(): string
    {
        return 'Lists the channels you can see, and checks the connection works.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channels = (array) ($this->cliq($run, $config, 'GET', '/channels', ['limit' => $this->limit($config, 50)])['channels'] ?? []);

        return ['channels' => array_map(fn (array $channel): array => ['id' => isset($channel['channel_id']) ? (string) $channel['channel_id'] : null, 'name' => $channel['name'] ?? null, 'unique_name' => $channel['unique_name'] ?? null, 'description' => $channel['description'] ?? null, 'chat_id' => $channel['chat_id'] ?? null, 'members' => $channel['participant_count'] ?? null], array_values(array_filter($channels, 'is_array'))), 'count' => count($channels)];
    }
}
