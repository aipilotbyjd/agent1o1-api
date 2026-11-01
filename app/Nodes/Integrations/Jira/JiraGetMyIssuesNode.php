<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetMyIssuesNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_my_issues';
    }

    public function name(): string
    {
        return 'Jira: Get My Issues';
    }

    public function description(): string
    {
        return 'Lists the issues assigned to the connected account.';
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

                'include_resolved' => Field::boolean('Include resolved issues'),
                'project' => Field::dynamic('Project', 'jira.projects', 'Only issues of this project.', 'APP'),
                'limit' => Field::integer('Max results', null, 25, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $clauses = ['assignee = currentUser()'];

        if (($config['include_resolved'] ?? false) !== true) {
            $clauses[] = 'resolution = Unresolved';
        }

        if (filled($config['project'] ?? null)) {
            $clauses[] = 'project = '.$this->jqlString($this->projectKey($config));
        }

        $issues = $this->searchIssues($run, $config, implode(' AND ', $clauses).' ORDER BY updated DESC', $this->limit($config, 25, 500));

        return ['issues' => $issues, 'count' => count($issues)];
    }
}
