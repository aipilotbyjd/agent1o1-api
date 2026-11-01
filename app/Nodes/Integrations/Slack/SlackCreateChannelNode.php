<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackCreateChannelNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_create_channel';
    }

    public function name(): string
    {
        return 'Slack: Create Channel';
    }

    public function description(): string
    {
        return 'Creates a new Slack channel, public or private.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Channel name', 'Lowercase, no spaces, up to 80 characters.', 'project-updates'),
                'is_private' => Field::boolean('Private channel'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, 'conversations.create', $config, array_filter([
            'name' => $config['name'],
            'is_private' => $config['is_private'] ?? null,
        ], fn ($value) => $value !== null));
    }
}
