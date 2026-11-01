<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordDeleteInviteNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_delete_invite';
    }

    public function name(): string
    {
        return 'Discord: Delete Invite';
    }

    public function description(): string
    {
        return 'Revokes an invite link.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['code'],
            'properties' => [
                ...$this->credentialFields(),
                'code' => Field::text('Invite code', 'The part after discord.gg/.', 'abc123'),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $code = basename(trim($this->requiredString($config, 'code', 'The invite code')));

        $this->discord($run, $config, 'DELETE', '/invites/'.rawurlencode($code), reason: $config['reason'] ?? null);

        return ['deleted' => true, 'code' => $code];
    }
}
