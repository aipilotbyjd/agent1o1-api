<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PipedriveListPipelinesNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_list_pipelines';
    }

    public function name(): string
    {
        return 'Pipedrive: List Pipelines and Stages';
    }

    public function description(): string
    {
        return 'Lists the pipelines with their stages and ids.';
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
        $pipelines = $this->listAll($run, $config, '/pipelines', [], 200);
        $stages = $this->listAll($run, $config, '/stages', [], 500);

        return ['pipelines' => array_map(fn (array $pipeline): array => ['id' => $pipeline['id'] ?? null, 'name' => $pipeline['name'] ?? null, 'active' => (bool) ($pipeline['active'] ?? true), 'stages' => array_values(array_map(fn (array $stage): array => ['id' => $stage['id'] ?? null, 'name' => $stage['name'] ?? null, 'order' => $stage['order_nr'] ?? null], array_filter($stages, fn (array $stage): bool => ($stage['pipeline_id'] ?? null) === ($pipeline['id'] ?? false))))], $pipelines), 'count' => count($pipelines)];
    }
}
