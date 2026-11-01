<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetProjectNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_project';
    }

    public function name(): string
    {
        return 'Jira: Get Project';
    }

    public function description(): string
    {
        return 'Gets one project, with its lead, type and description.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeProject($this->request($run, $config, 'GET', '/project/'.rawurlencode($this->projectKey($config)), ['expand' => 'description,lead']));
    }
}
