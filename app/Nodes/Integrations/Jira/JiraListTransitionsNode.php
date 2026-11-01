<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListTransitionsNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_transitions';
    }

    public function name(): string
    {
        return 'Jira: List Transitions';
    }

    public function description(): string
    {
        return 'Lists the statuses an issue can move to right now, and the transition for each.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->request($run, $config, 'GET', '/issue/'.rawurlencode($this->issueKey($config)).'/transitions');

        $transitions = array_values(array_map(fn (array $transition): array => [
            'id' => $transition['id'] ?? null,
            'name' => $transition['name'] ?? null,
            'to_status' => data_get($transition, 'to.name'),
            'to_status_category' => data_get($transition, 'to.statusCategory.name'),
            'has_screen' => $transition['hasScreen'] ?? null,
        ], array_filter((array) ($answer['transitions'] ?? []), 'is_array')));

        return ['transitions' => $transitions, 'count' => count($transitions)];
    }
}
