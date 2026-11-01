<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordCreateInviteNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_create_invite';
    }

    public function name(): string
    {
        return 'Discord: Create Invite';
    }

    public function description(): string
    {
        return 'Creates an invite link to a channel, with an optional expiry and use limit.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField('Channel', 'The channel people land in.'),
                'expires_in_hours' => Field::integer('Expires after (hours)', 'Up to 168 (7 days). 0 never expires.', 24, 0, 168),
                'max_uses' => Field::integer('Max uses', '0 for no limit.', 0, 0, 100),
                'temporary' => Field::advanced(Field::boolean('Temporary membership', 'People are removed when they go offline unless given a role.')),
                'unique' => Field::advanced(Field::boolean('Always a new link', 'Do not reuse a similar existing invite.', true)),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');

        $invite = (array) $this->discord($run, $config, 'POST', "/channels/{$channel}/invites", [
            'max_age' => (int) ($config['expires_in_hours'] ?? 24) * 3600,
            'max_uses' => (int) ($config['max_uses'] ?? 0),
            'temporary' => filter_var($config['temporary'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'unique' => filter_var($config['unique'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ], reason: $config['reason'] ?? null);

        return ['code' => $invite['code'] ?? null, 'url' => isset($invite['code']) ? 'https://discord.gg/'.$invite['code'] : null, 'channel_id' => $channel, 'max_uses' => $invite['max_uses'] ?? null, 'expires_at' => $invite['expires_at'] ?? null];
    }
}
