<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class JiraListPrioritiesNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_priorities';
    }

    public function name(): string
    {
        return 'Jira: List Priorities';
    }

    public function description(): string
    {
        return 'Lists the priorities issues can have.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $priorities = $this->listPages($run, $config, '/priority/search', [], 'values', 100);

        return ['priorities' => array_map(fn (array $priority): array => ['id' => $priority['id'] ?? null, 'name' => $priority['name'] ?? null, 'description' => $priority['description'] ?? null], $priorities), 'count' => count($priorities)];
    }
}
