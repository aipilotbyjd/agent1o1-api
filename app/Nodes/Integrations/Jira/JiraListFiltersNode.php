<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListFiltersNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_filters';
    }

    public function name(): string
    {
        return 'Jira: List Saved Filters';
    }

    public function description(): string
    {
        return 'Lists the saved filters the account can use, with their JQL.';
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

                'query' => Field::text('Name contains', 'Only filters whose name has this text.'),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $filters = $this->listPages($run, $config, '/filter/search', ['filterName' => $config['query'] ?? null, 'expand' => 'jql'], 'values', $this->limit($config, 50, 500));

        return ['filters' => array_map(fn (array $filter): array => ['id' => $filter['id'] ?? null, 'name' => $filter['name'] ?? null, 'jql' => $filter['jql'] ?? null, 'owner' => data_get($filter, 'owner.displayName'), 'description' => $filter['description'] ?? null], $filters), 'count' => count($filters)];
    }
}
