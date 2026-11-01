<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AirtableGetRecordNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_get_record';
    }

    public function name(): string
    {
        return 'Airtable: Get Record';
    }

    public function description(): string
    {
        return 'Reads one record.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table', 'record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'record_id' => Field::text('Record', 'The record id (starts with rec), from a previous step.', 'recAbc123def456'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeRecord($this->airtable($run, $config, 'GET', $this->tablePath($config).'/'.$this->recordId($config)));
    }
}
