<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackInviteToChannelNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_invite_to_channel';
    }

    public function name(): string
    {
        return 'Slack: Invite to Channel';
    }

    public function description(): string
    {
        return 'Invites one or more users to a Slack channel.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'users'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'The channel to use. Pick one, or enter a channel ID.', 'C0123456789'),
                'users' => Field::dynamic('Users', 'slack.users', 'The people to invite.', 'U0123456789', multiple: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'conversations.invite', $config, [
            'channel' => $config['channel'],
            'users' => $config['users'],
        ]);
    }
}
