<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackGetUserNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_get_user';
    }

    public function name(): string
    {
        return 'Slack: Get User';
    }

    public function description(): string
    {
        return 'Reads a person: their name, title, time zone and email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user'],
            'properties' => [
                ...$this->credentialFields(),
                'user' => Field::dynamic('Person', 'slack.users', 'Pick a person, or enter a Slack user ID.', 'U0123456789'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, 'users.info', $config, ['user' => $config['user']]);
    }
}
