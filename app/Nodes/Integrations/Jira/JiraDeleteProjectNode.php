<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraDeleteProjectNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_delete_project';
    }

    public function name(): string
    {
        return 'Jira: Delete Project';
    }

    public function description(): string
    {
        return 'Deletes a project and its issues. With "Keep in the trash" it can be restored for a while.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'project' => Field::dynamic('Project', 'jira.projects', 'Pick a project, or give its key, e.g. APP.', 'APP'),
                'enable_undo' => Field::boolean('Keep in the trash', 'Lets the project be restored from Jira\'s trash. Turn off to delete it for good.', true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $this->projectKey($config);

        $this->request($run, $config, 'DELETE', '/project/'.rawurlencode($key), ['enableUndo' => ($config['enable_undo'] ?? true) === false ? 'false' : 'true']);

        return ['deleted' => true, 'key' => $key];
    }
}
