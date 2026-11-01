<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordSearchMembersNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_search_members';
    }

    public function name(): string
    {
        return 'Discord: Search Members';
    }

    public function description(): string
    {
        return 'Finds members of a server whose username or nickname starts with some text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild', 'query'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'query' => Field::text('Starts with', 'Part of the username or nickname, from its start.', 'ada'),
                'limit' => Field::integer('Max results', null, 10, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $members = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/members/search", query: ['query' => $this->requiredString($config, 'query', 'The search text'), 'limit' => $this->limit($config, 10, 1000)]), 'is_array'));

        return ['members' => array_map(fn (array $member): array => $this->shapeMember($member), $members), 'count' => count($members)];
    }
}
