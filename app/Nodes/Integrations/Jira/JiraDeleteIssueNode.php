<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraDeleteIssueNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_delete_issue';
    }

    public function name(): string
    {
        return 'Jira: Delete Issue';
    }

    public function description(): string
    {
        return 'Deletes an issue for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'delete_subtasks' => Field::boolean('Delete its sub-tasks too', 'Jira refuses to delete an issue that has sub-tasks unless this is on.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $this->issueKey($config);

        $this->request($run, $config, 'DELETE', '/issue/'.rawurlencode($key), ['deleteSubtasks' => ($config['delete_subtasks'] ?? false) === true ? 'true' : 'false']);

        return ['deleted' => true, 'key' => $key];
    }
}
