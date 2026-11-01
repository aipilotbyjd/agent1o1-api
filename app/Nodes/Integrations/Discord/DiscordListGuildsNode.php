<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordListGuildsNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_guilds';
    }

    public function name(): string
    {
        return 'Discord: List Servers';
    }

    public function description(): string
    {
        return 'Lists the servers the bot is in, with their member counts.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guilds = array_values(array_filter((array) $this->discord($run, $config, 'GET', '/users/@me/guilds', query: ['limit' => $this->limit($config, 100, 200), 'with_counts' => 'true']), 'is_array'));

        return ['guilds' => array_map(fn (array $guild): array => [
            'id' => $guild['id'] ?? null,
            'name' => $guild['name'] ?? null,
            'owner' => $guild['owner'] ?? false,
            'member_count' => $guild['approximate_member_count'] ?? null,
            'online_count' => $guild['approximate_presence_count'] ?? null,
        ], $guilds), 'count' => count($guilds)];
    }
}
