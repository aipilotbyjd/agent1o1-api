<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListServiceDesksNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_service_desks';
    }

    public function name(): string
    {
        return 'Jira: List Service Desks';
    }

    public function description(): string
    {
        return 'Lists the Jira Service Management service desks the account can see.';
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

                'limit' => Field::integer('Max results', null, 50, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $desks = $this->listDeskPages($run, $config, '/servicedesk', [], $this->limit($config, 50, 200));

        return ['service_desks' => array_map(fn (array $desk): array => ['id' => $desk['id'] ?? null, 'project_id' => $desk['projectId'] ?? null, 'project_key' => $desk['projectKey'] ?? null, 'name' => $desk['projectName'] ?? null], $desks), 'count' => count($desks)];
    }
}
