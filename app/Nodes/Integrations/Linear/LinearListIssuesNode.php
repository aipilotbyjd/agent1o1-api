<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListIssuesNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_issues';
    }

    public function name(): string
    {
        return 'Linear: List Issues';
    }

    public function description(): string
    {
        return 'Lists issues, optionally narrowed by team, status, project, priority, labels or assignee.';
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
                ...$this->issueFilterFields(),
                'order_by' => Field::select('Sort by', ['updatedAt' => 'Recently updated', 'createdAt' => 'Recently created'], default: 'updatedAt'),
                'limit' => Field::integer('Max results', null, 10, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $orderBy = ($config['order_by'] ?? 'updatedAt') === 'createdAt' ? 'createdAt' : 'updatedAt';

        $issues = $this->listAll($run, $config, $this->issuesQuery($orderBy), ['filter' => $this->issueFilter($run, $config) ?: null], 'issues', $this->limit($config, 10));

        return ['issues' => array_map($this->shapeIssue(...), $issues), 'count' => count($issues)];
    }
}
