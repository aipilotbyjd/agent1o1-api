<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveListLeadsNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_list_leads';
    }

    public function name(): string
    {
        return 'Pipedrive: List Leads';
    }

    public function description(): string
    {
        return 'Lists leads, newest first.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $records = $this->listAll($run, $config, '/leads', ['sort' => 'add_time DESC'], $this->limit($config, 50));

        return ['leads' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
