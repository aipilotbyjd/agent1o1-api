<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DiscordListRolesNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_roles';
    }

    public function name(): string
    {
        return 'Discord: List Roles';
    }

    public function description(): string
    {
        return 'Lists the roles of a server, highest first.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $roles = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/roles"), 'is_array'));
        usort($roles, fn (array $a, array $b): int => ($b['position'] ?? 0) <=> ($a['position'] ?? 0));

        return ['roles' => array_map(fn (array $role): array => $this->shapeRole($role), $roles), 'count' => count($roles)];
    }
}
