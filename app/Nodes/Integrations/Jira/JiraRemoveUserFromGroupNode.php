<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraRemoveUserFromGroupNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_remove_user_from_group';
    }

    public function name(): string
    {
        return 'Jira: Remove User from Group';
    }

    public function description(): string
    {
        return 'Takes a person out of a user group.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['group', 'user'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'group' => Field::dynamic('Group', 'jira.groups', 'Pick a group, or give its name.', 'jira-developers'),
                'user' => Field::dynamic('Person', 'jira.users', 'Pick someone, or give an email, name or account id.', 'ada@acme.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $account = $this->resolveUser($run, $config, $this->requiredString($config, 'user', 'The person'));
        $group = $this->requiredString($config, 'group', 'The group');

        $this->request($run, $config, 'DELETE', '/group/user', ['groupname' => $group, 'accountId' => $account]);

        return ['group' => $group, 'account_id' => $account, 'removed' => true];
    }
}
