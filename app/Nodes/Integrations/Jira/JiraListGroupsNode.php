<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListGroupsNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_groups';
    }

    public function name(): string
    {
        return 'Jira: List Groups';
    }

    public function description(): string
    {
        return 'Lists the user groups of the Jira site.';
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
                ...$this->siteFields(),

                'query' => Field::text('Name contains', 'Only groups whose name has this text.'),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $groups = $this->listPages($run, $config, '/group/bulk', ['query' => $config['query'] ?? null], 'values', $this->limit($config, 50, 500));

        return ['groups' => array_map(fn (array $group): array => ['id' => $group['groupId'] ?? null, 'name' => $group['name'] ?? null], $groups), 'count' => count($groups)];
    }
}
