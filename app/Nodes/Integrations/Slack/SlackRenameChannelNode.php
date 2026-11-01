<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackRenameChannelNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_rename_channel';
    }

    public function name(): string
    {
        return 'Slack: Rename Channel';
    }

    public function description(): string
    {
        return 'Changes a channel\'s name.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'Pick a channel, or enter a channel ID.', 'C0123456789'),
                'name' => Field::text('New name', 'Lowercase letters, numbers, hyphens and underscores, up to 80 characters.', 'project-launch'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'conversations.rename', $config, ['channel' => $config['channel'], 'name' => $config['name']]);
    }
}
