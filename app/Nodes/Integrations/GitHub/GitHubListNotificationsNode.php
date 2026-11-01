<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubListNotificationsNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_list_notifications';
    }

    public function name(): string
    {
        return 'GitHub: List Notifications';
    }

    public function description(): string
    {
        return 'Lists the connected account’s unread notifications.';
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
                'all' => Field::boolean('Include read notifications'),
                'participating' => Field::boolean('Only where I am involved'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['notifications' => $this->get($run, '/notifications', $config, [
            'all' => ($config['all'] ?? false) ? 'true' : null,
            'participating' => ($config['participating'] ?? false) ? 'true' : null,
            'per_page' => $config['per_page'] ?? 30,
        ])];
    }
}
