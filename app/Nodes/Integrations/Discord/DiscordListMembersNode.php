<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordListMembersNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_members';
    }

    public function name(): string
    {
        return 'Discord: List Members';
    }

    public function description(): string
    {
        return 'Lists the members of a server (needs the bot\'s "Server Members" intent switched on in the Developer Portal).';
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
                'after' => Field::advanced(Field::text('After person', 'Continue after this person\'s id, to read the next page.')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $members = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/members", query: ['limit' => $this->limit($config, 100, 1000), 'after' => $this->id($config, 'after', 'The person', required: false)]), 'is_array'));

        return ['members' => array_map(fn (array $member): array => $this->shapeMember($member), $members), 'count' => count($members), 'last_id' => data_get(end($members) ?: [], 'user.id')];
    }
}
