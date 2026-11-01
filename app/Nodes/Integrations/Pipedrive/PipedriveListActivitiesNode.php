<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveListActivitiesNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_list_activities';
    }

    public function name(): string
    {
        return 'Pipedrive: List Activities';
    }

    public function description(): string
    {
        return 'Lists activities, newest first.';
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
        $records = $this->listAll($run, $config, '/activities', ['user_id' => 0, 'sort' => 'add_time DESC'], $this->limit($config, 50));

        return ['activities' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
