<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordGetUserNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_get_user';
    }

    public function name(): string
    {
        return 'Discord: Get User';
    }

    public function description(): string
    {
        return 'Gets a Discord account\'s username, display name and avatar from its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => Field::text('Person', 'Their id.', '123456789012345678'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeUser((array) $this->discord($run, $config, 'GET', '/users/'.$this->id($config, 'user_id', 'The person')));
    }
}
