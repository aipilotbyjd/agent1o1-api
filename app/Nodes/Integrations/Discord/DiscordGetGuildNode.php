<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DiscordGetGuildNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_get_guild';
    }

    public function name(): string
    {
        return 'Discord: Get Server';
    }

    public function description(): string
    {
        return 'Gets a server\'s name, owner, member counts and settings.';
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
        $info = (array) $this->discord($run, $config, 'GET', "/guilds/{$guild}", query: ['with_counts' => 'true']);

        return [
            'id' => $info['id'] ?? null,
            'name' => $info['name'] ?? null,
            'description' => $info['description'] ?? null,
            'owner_id' => $info['owner_id'] ?? null,
            'member_count' => $info['approximate_member_count'] ?? null,
            'online_count' => $info['approximate_presence_count'] ?? null,
            'boost_level' => $info['premium_tier'] ?? 0,
            'boost_count' => $info['premium_subscription_count'] ?? 0,
            'preferred_locale' => $info['preferred_locale'] ?? null,
            'vanity_url' => $info['vanity_url_code'] ?? null,
            'created_at' => $this->snowflakeTime($info['id'] ?? null),
        ];
    }
}
