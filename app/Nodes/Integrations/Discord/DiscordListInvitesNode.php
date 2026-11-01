<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DiscordListInvitesNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_invites';
    }

    public function name(): string
    {
        return 'Discord: List Invites';
    }

    public function description(): string
    {
        return 'Lists the active invites of a server, with their uses.';
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
        $invites = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/invites"), 'is_array'));

        return ['invites' => array_map(fn (array $invite): array => [
            'code' => $invite['code'] ?? null,
            'url' => isset($invite['code']) ? 'https://discord.gg/'.$invite['code'] : null,
            'channel_id' => data_get($invite, 'channel.id'),
            'channel' => data_get($invite, 'channel.name'),
            'created_by' => data_get($invite, 'inviter.username'),
            'uses' => $invite['uses'] ?? 0,
            'max_uses' => $invite['max_uses'] ?? 0,
            'expires_at' => $invite['expires_at'] ?? null,
            'created_at' => $invite['created_at'] ?? null,
        ], $invites), 'count' => count($invites)];
    }
}
