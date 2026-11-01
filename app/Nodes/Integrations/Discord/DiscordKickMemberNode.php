<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordKickMemberNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_kick_member';
    }

    public function name(): string
    {
        return 'Discord: Kick Member';
    }

    public function description(): string
    {
        return 'Removes a person from a server. They can join again with an invite.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'user_id' => Field::text('Person', 'Their id.', '123456789012345678'),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $user = $this->id($config, 'user_id', 'The person');

        $this->discord($run, $config, 'DELETE', "/guilds/{$guild}/members/{$user}", reason: $config['reason'] ?? null);

        return ['kicked' => true, 'guild' => $guild, 'user_id' => $user];
    }
}
