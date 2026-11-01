<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SlackFindUserByEmailNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_find_user_by_email';
    }

    public function name(): string
    {
        return 'Slack: Find User by Email';
    }

    public function description(): string
    {
        return 'Finds the Slack person with an email address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email address', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, 'users.lookupByEmail', $config, ['email' => trim((string) $config['email'])]);
    }
}
