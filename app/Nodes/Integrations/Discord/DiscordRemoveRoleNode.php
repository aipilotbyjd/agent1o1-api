<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordRemoveRoleNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_remove_role';
    }

    public function name(): string
    {
        return 'Discord: Remove Role from Member';
    }

    public function description(): string
    {
        return 'Takes a role away from a person.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild', 'user_id', 'role_id'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'user_id' => Field::text('Person', 'Their id.', '123456789012345678'),
                'role_id' => Field::dynamic('Role', 'discord.roles', 'Pick a role, or map its id.', 'Moderator', dependsOn: ['guild']),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $user = $this->id($config, 'user_id', 'The person');
        $role = $this->id($config, 'role_id', 'The role');

        $this->discord($run, $config, 'DELETE', "/guilds/{$guild}/members/{$user}/roles/{$role}", reason: $config['reason'] ?? null);

        return ['removed' => true, 'guild' => $guild, 'user_id' => $user, 'role_id' => $role];
    }
}
