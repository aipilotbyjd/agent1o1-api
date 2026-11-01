<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetRequestTypesNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_request_types';
    }

    public function name(): string
    {
        return 'Jira: Get Request Types';
    }

    public function description(): string
    {
        return 'Lists the kinds of request a service desk takes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['service_desk'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),
                'service_desk' => Field::dynamic('Service desk', 'jira.service_desks', 'Pick a service desk, or give its id.', '1'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $types = $this->listDeskPages($run, $config, '/servicedesk/'.rawurlencode($this->requiredString($config, 'service_desk', 'The service desk')).'/requesttype', [], 200);

        return ['request_types' => array_map(fn (array $type): array => ['id' => $type['id'] ?? null, 'name' => $type['name'] ?? null, 'description' => $type['description'] ?? null, 'help_text' => $type['helpText'] ?? null, 'group_ids' => $type['groupIds'] ?? []], $types), 'count' => count($types)];
    }
}
