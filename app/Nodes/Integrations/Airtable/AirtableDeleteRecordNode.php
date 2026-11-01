<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AirtableDeleteRecordNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_delete_record';
    }

    public function name(): string
    {
        return 'Airtable: Delete Record';
    }

    public function description(): string
    {
        return 'Deletes a record. This can\'t be undone from here (Airtable keeps it in the base\'s trash for a while).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->recordId($config);

        $this->airtable($run, $config, 'DELETE', $this->tablePath($config).'/'.$id);

        return ['deleted' => true, 'record_id' => $id];
    }
}
