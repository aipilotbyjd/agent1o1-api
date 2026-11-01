<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordDeleteRoleNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_delete_role';
    }

    public function name(): string
    {
        return 'Discord: Delete Role';
    }

    public function description(): string
    {
        return 'Deletes a role from a server.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild', 'role_id'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'role_id' => Field::dynamic('Role', 'discord.roles', 'Pick a role, or map its id.', 'Moderator', dependsOn: ['guild']),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $role = $this->id($config, 'role_id', 'The role');

        $this->discord($run, $config, 'DELETE', "/guilds/{$guild}/roles/{$role}", reason: $config['reason'] ?? null);

        return ['deleted' => true, 'guild' => $guild, 'role_id' => $role];
    }
}
