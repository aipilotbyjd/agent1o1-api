<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraDeleteIssueLinkNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_delete_issue_link';
    }

    public function name(): string
    {
        return 'Jira: Delete Issue Link';
    }

    public function description(): string
    {
        return 'Removes a link between two issues.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['link_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'link_id' => Field::text('Link id', 'The "id" of a link from Get Issue Links.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requiredString($config, 'link_id', 'The link id');

        $this->request($run, $config, 'DELETE', '/issueLink/'.rawurlencode($id));

        return ['deleted' => true, 'id' => $id];
    }
}
