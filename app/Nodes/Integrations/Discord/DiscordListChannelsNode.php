<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordListChannelsNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_channels';
    }

    public function name(): string
    {
        return 'Discord: List Channels';
    }

    public function description(): string
    {
        return 'Lists the channels of a server, in the order Discord shows them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'channel_type' => Field::select('Kind', ['all' => 'All', 'text' => 'Text', 'voice' => 'Voice', 'category' => 'Categories', 'announcement' => 'Announcement', 'forum' => 'Forum', 'stage' => 'Stage'], null, 'all'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $type = (string) ($config['channel_type'] ?? 'all');
        $channels = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/channels"), 'is_array'));

        $channels = array_values(array_filter($channels, fn (array $channel): bool => $type === 'all' || $type === '' || $this->channelTypeName($channel['type'] ?? null) === $type));
        usort($channels, fn (array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return ['channels' => array_map(fn (array $channel): array => $this->shapeChannel([...$channel, 'guild_id' => $guild]), $channels), 'count' => count($channels)];
    }
}
