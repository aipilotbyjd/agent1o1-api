<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordListActiveThreadsNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_active_threads';
    }

    public function name(): string
    {
        return 'Discord: List Active Threads';
    }

    public function description(): string
    {
        return 'Lists the threads of a server that are not archived, newest first, optionally of one channel.';
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
                'channel_id' => Field::advanced($this->channelField('Only in channel', 'Only threads whose parent is this channel.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $parent = $this->id($config, 'channel_id', 'The channel', required: false);
        $threads = array_values(array_filter((array) data_get((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/threads/active"), 'threads', []), 'is_array'));

        $threads = array_values(array_filter($threads, fn (array $thread): bool => $parent === null || ($thread['parent_id'] ?? null) === $parent));
        usort($threads, fn (array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);

        return ['threads' => array_map(fn (array $thread): array => $this->shapeChannel([...$thread, 'guild_id' => $guild]), $threads), 'count' => count($threads)];
    }
}
