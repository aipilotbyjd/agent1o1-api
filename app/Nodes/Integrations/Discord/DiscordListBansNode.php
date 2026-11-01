<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordListBansNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_bans';
    }

    public function name(): string
    {
        return 'Discord: List Bans';
    }

    public function description(): string
    {
        return 'Lists the people banned from a server, with the reason.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $bans = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/bans", query: ['limit' => $this->limit($config, 100, 1000)]), 'is_array'));

        return ['bans' => array_map(fn (array $ban): array => [...$this->shapeUser((array) ($ban['user'] ?? [])), 'reason' => $ban['reason'] ?? null], $bans), 'count' => count($bans)];
    }
}
