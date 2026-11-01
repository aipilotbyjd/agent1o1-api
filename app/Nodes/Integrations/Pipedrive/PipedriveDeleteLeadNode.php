<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveDeleteLeadNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_delete_lead';
    }

    public function name(): string
    {
        return 'Pipedrive: Delete Lead';
    }

    public function description(): string
    {
        return 'Deletes a lead. Pipedrive keeps it for 30 days.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Lead id', 'The lead id (a UUID).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->leadId($config);

        $this->pipedrive($run, $config, 'DELETE', "/leads/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
