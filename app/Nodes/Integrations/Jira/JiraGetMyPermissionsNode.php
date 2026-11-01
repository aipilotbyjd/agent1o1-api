<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetMyPermissionsNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_my_permissions';
    }

    public function name(): string
    {
        return 'Jira: Get My Permissions';
    }

    public function description(): string
    {
        return 'Checks what the connected account may do, in a project or on an issue.';
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

                'project' => Field::dynamic('Project', 'jira.projects', 'Check in this project.', 'APP'),
                'issue' => Field::text('Issue', 'Or check on this issue, e.g. APP-123.'),
                'permissions' => Field::text('Permissions', 'Comma-separated permission keys.', 'CREATE_ISSUES, EDIT_ISSUES, ADD_COMMENTS'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $keys = $this->csv($config['permissions'] ?? null) ?: ['BROWSE_PROJECTS', 'CREATE_ISSUES', 'EDIT_ISSUES', 'ADD_COMMENTS', 'ASSIGN_ISSUES', 'TRANSITION_ISSUES', 'DELETE_ISSUES', 'ADMINISTER_PROJECTS'];

        $answer = $this->request($run, $config, 'GET', '/mypermissions', [
            'projectKey' => filled($config['project'] ?? null) ? $this->projectKey($config) : null,
            'issueKey' => filled($config['issue'] ?? null) ? $this->issueKey($config) : null,
            'permissions' => implode(',', $keys),
        ]);

        $permissions = [];

        foreach ((array) ($answer['permissions'] ?? []) as $key => $permission) {
            $permissions[(string) $key] = (bool) ($permission['havePermission'] ?? false);
        }

        return ['permissions' => $permissions, 'granted' => array_keys(array_filter($permissions))];
    }
}
