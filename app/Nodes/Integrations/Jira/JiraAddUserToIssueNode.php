<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class JiraAddUserToIssueNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_add_user_to_issue';
    }

    public function name(): string
    {
        return 'Jira: Add User to Issue';
    }

    public function description(): string
    {
        return 'Makes a person the assignee or reporter of an issue, or adds them as a watcher.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue', 'role', 'user'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'role' => Field::select('As', ['assignee' => 'Assignee', 'reporter' => 'Reporter', 'watcher' => 'Watcher'], default: 'assignee'),
                'user' => Field::dynamic('Person', 'jira.users', 'Pick someone, or give an email, name or account id.', 'ada@acme.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $this->issueKey($config);
        $role = (string) ($config['role'] ?? 'assignee');
        $account = $this->resolveUser($run, $config, $this->requiredString($config, 'user', 'The person'));

        match ($role) {
            'assignee' => $this->request($run, $config, 'PUT', '/issue/'.rawurlencode($key).'/assignee', [], ['accountId' => $account]),
            'reporter' => $this->request($run, $config, 'PUT', '/issue/'.rawurlencode($key), [], ['fields' => ['reporter' => ['accountId' => $account]]]),
            'watcher' => $this->request($run, $config, 'POST', '/issue/'.rawurlencode($key).'/watchers', [], $account),
            default => throw new InvalidArgumentException("\"{$role}\" is not assignee, reporter or watcher."),
        };

        return ['key' => $key, 'role' => $role, 'account_id' => $account];
    }
}
