<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ActiveCampaignListPipelinesNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_list_pipelines';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Pipelines';
    }

    public function description(): string
    {
        return 'Lists the deal pipelines with their stages.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $pipelines = $this->listAll($run, $config, '/dealGroups', 'dealGroups', [], 100);
        $stages = $this->listAll($run, $config, '/dealStages', 'dealStages', [], 1000);

        return ['pipelines' => array_map(fn (array $pipeline): array => [
            'id' => (int) $pipeline['id'],
            'name' => $pipeline['title'] ?? null,
            'currency' => $pipeline['currency'] ?? null,
            'stages' => array_values(array_map(fn (array $stage): array => ['id' => (int) $stage['id'], 'name' => $stage['title'] ?? null, 'order' => isset($stage['order']) ? (int) $stage['order'] : null], array_filter($stages, fn (array $stage): bool => (int) ($stage['group'] ?? 0) === (int) $pipeline['id']))),
        ], $pipelines), 'count' => count($pipelines)];
    }
}
