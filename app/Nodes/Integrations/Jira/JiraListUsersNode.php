<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListUsersNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_users';
    }

    public function name(): string
    {
        return 'Jira: List Users';
    }

    public function description(): string
    {
        return 'Lists the people of the Jira site, or finds some by name or email.';
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

                'query' => Field::text('Name or email contains', 'Leave empty to list everyone.'),
                'include_inactive' => Field::boolean('Include people who left'),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $limit = $this->limit($config, 50, 500);

        $users = filled($config['query'] ?? null)
            ? array_values(array_filter($this->request($run, $config, 'GET', '/user/search', ['query' => (string) $config['query'], 'maxResults' => min(100, $limit)]), 'is_array'))
            : $this->listPages($run, $config, '/users/search', [], null, $limit);

        $users = array_values(array_filter($users, fn (array $user): bool => ($user['accountType'] ?? 'atlassian') === 'atlassian' && (($config['include_inactive'] ?? false) === true || ($user['active'] ?? true) === true)));

        return ['users' => array_map($this->shapeUser(...), $users), 'count' => count($users)];
    }
}
