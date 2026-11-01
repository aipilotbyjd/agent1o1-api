<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordUnbanMemberNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_unban_member';
    }

    public function name(): string
    {
        return 'Discord: Unban Member';
    }

    public function description(): string
    {
        return 'Lifts a ban.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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

        $this->discord($run, $config, 'DELETE', "/guilds/{$guild}/bans/{$user}", reason: $config['reason'] ?? null);

        return ['unbanned' => true, 'guild' => $guild, 'user_id' => $user];
    }
}
