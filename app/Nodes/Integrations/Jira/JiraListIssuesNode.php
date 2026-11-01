<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class JiraListIssuesNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_issues';
    }

    public function name(): string
    {
        return 'Jira: List Issues';
    }

    public function description(): string
    {
        return 'Lists issues chosen by fields, a JQL query or a saved filter.';
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

                ...$this->issueFilterFields(),
                'order_by' => Field::select('Sort by', ['updated DESC' => 'Recently updated', 'created DESC' => 'Recently created', 'priority DESC' => 'Highest priority', 'key ASC' => 'Issue key'], default: 'updated DESC'),
                'limit' => Field::integer('Max results', null, 10, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $where = $this->filterJql($run, $config);

        if ($where === '') {
            throw new InvalidArgumentException('Choose a project or another filter: Jira does not search every issue at once.');
        }

        $order = in_array((string) ($config['order_by'] ?? ''), ['updated DESC', 'created DESC', 'priority DESC', 'key ASC'], true) ? (string) $config['order_by'] : 'updated DESC';
        $issues = $this->searchIssues($run, $config, "{$where} ORDER BY {$order}", $this->limit($config, 10, 500));

        return ['issues' => $issues, 'count' => count($issues)];
    }
}
