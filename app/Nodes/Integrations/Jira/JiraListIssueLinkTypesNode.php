<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class JiraListIssueLinkTypesNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_issue_link_types';
    }

    public function name(): string
    {
        return 'Jira: List Issue Link Types';
    }

    public function description(): string
    {
        return 'Lists the ways issues can be linked (Blocks, Duplicate, Relates...).';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $types = array_values(array_filter((array) ($this->request($run, $config, 'GET', '/issueLinkType')['issueLinkTypes'] ?? []), 'is_array'));

        return ['link_types' => array_map(fn (array $type): array => ['id' => $type['id'] ?? null, 'name' => $type['name'] ?? null, 'inward' => $type['inward'] ?? null, 'outward' => $type['outward'] ?? null], $types), 'count' => count($types)];
    }
}
