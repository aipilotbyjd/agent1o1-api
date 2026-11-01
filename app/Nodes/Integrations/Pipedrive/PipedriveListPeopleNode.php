<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveListPeopleNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_list_persons';
    }

    public function name(): string
    {
        return 'Pipedrive: List People';
    }

    public function description(): string
    {
        return 'Lists persons, newest first.';
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
        $records = $this->listAll($run, $config, '/persons', ['sort' => 'add_time DESC'], $this->limit($config, 50));

        return ['persons' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
