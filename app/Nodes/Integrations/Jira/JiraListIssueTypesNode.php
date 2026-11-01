<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListIssueTypesNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_issue_types';
    }

    public function name(): string
    {
        return 'Jira: List Issue Types';
    }

    public function description(): string
    {
        return 'Lists the issue types of a project, or every type of the site.';
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

                'project' => Field::dynamic('Project', 'jira.projects', 'Only the types this project offers. Leave empty for every type.', 'APP'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $types = filled($config['project'] ?? null)
            ? array_values(array_filter((array) ($this->request($run, $config, 'GET', '/issue/createmeta/'.rawurlencode($this->projectKey($config)).'/issuetypes', ['maxResults' => 100])['issueTypes'] ?? []), 'is_array'))
            : array_values(array_filter($this->request($run, $config, 'GET', '/issuetype'), 'is_array'));

        return ['issue_types' => array_map(fn (array $type): array => ['id' => $type['id'] ?? null, 'name' => $type['name'] ?? null, 'description' => $type['description'] ?? null, 'subtask' => $type['subtask'] ?? false, 'hierarchy_level' => $type['hierarchyLevel'] ?? null], $types), 'count' => count($types)];
    }
}
