<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraSearchIssuesNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_search_issues';
    }

    public function name(): string
    {
        return 'Jira: Search Issues (JQL)';
    }

    public function description(): string
    {
        return 'Finds issues with a JQL query.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['jql'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'jql' => Field::textarea('JQL', 'A Jira Query Language search, e.g. project = APP AND status = "In Progress" ORDER BY updated DESC.', 'project = APP AND priority = High'),
                'fields' => Field::advanced(Field::text('Fields to read', 'Comma-separated Jira field ids. Leave empty for the usual ones.')),
                'limit' => Field::integer('Max results', null, 25, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->csv($config['fields'] ?? null);
        $issues = $this->searchIssues($run, $config, $this->requiredString($config, 'jql', 'The JQL query'), $this->limit($config, 25, 500), $fields === [] ? null : $fields);

        return ['issues' => $issues, 'count' => count($issues)];
    }
}
