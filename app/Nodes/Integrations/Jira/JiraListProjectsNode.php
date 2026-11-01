<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListProjectsNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_projects';
    }

    public function name(): string
    {
        return 'Jira: List Projects';
    }

    public function description(): string
    {
        return 'Lists the projects the account can see.';
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

                'query' => Field::text('Name or key contains', 'Only projects matching this text.'),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $projects = $this->listPages($run, $config, '/project/search', ['query' => $config['query'] ?? null, 'expand' => 'description,lead'], 'values', $this->limit($config, 50, 500));

        return ['projects' => array_map($this->shapeProject(...), $projects), 'count' => count($projects)];
    }
}
