<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class JiraGetMyselfNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_myself';
    }

    public function name(): string
    {
        return 'Jira: Get Current User';
    }

    public function description(): string
    {
        return 'Gets the person the Jira account belongs to.';
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
        return $this->shapeUser($this->request($run, $config, 'GET', '/myself'));
    }
}
