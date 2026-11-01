<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraCreateGroupNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_create_group';
    }

    public function name(): string
    {
        return 'Jira: Create Group';
    }

    public function description(): string
    {
        return 'Creates a user group.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'name' => Field::text('Group name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $group = $this->request($run, $config, 'POST', '/group', [], ['name' => $this->requiredString($config, 'name', 'The group name')]);

        return ['id' => $group['groupId'] ?? null, 'name' => $group['name'] ?? null];
    }
}
