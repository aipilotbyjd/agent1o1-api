<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordGetMemberNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_get_member';
    }

    public function name(): string
    {
        return 'Discord: Get Member';
    }

    public function description(): string
    {
        return 'Gets a person\'s nickname, roles and join date in a server.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $user = $this->id($config, 'user_id', 'The person');

        return $this->shapeMember((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/members/{$user}"));
    }
}
