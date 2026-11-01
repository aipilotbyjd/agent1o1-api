<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DiscordGetMeNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_get_me';
    }

    public function name(): string
    {
        return 'Discord: Get Bot';
    }

    public function description(): string
    {
        return 'Gets the bot\'s own id and name, and how many servers it is in.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $bot = (array) $this->discord($run, $config, 'GET', '/users/@me');
        $guilds = (array) $this->discord($run, $config, 'GET', '/users/@me/guilds', query: ['limit' => 200]);

        return [...$this->shapeUser($bot), 'server_count' => count($guilds)];
    }
}
