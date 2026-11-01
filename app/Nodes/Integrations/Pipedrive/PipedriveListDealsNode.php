<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveListDealsNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_list_deals';
    }

    public function name(): string
    {
        return 'Pipedrive: List Deals';
    }

    public function description(): string
    {
        return 'Lists deals, newest first.';
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
                'status' => Field::advanced(Field::select('Show', ['any' => 'All', 'open' => 'Open', 'won' => 'Won', 'lost' => 'Lost'], null, 'any')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = in_array($config['status'] ?? 'any', ['open', 'won', 'lost'], true) ? $config['status'] : 'any';
        $records = $this->listAll($run, $config, '/deals', ['status' => $status === 'any' ? 'all_not_deleted' : $status, 'sort' => 'add_time DESC'], $this->limit($config, 50));

        return ['deals' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }
}
