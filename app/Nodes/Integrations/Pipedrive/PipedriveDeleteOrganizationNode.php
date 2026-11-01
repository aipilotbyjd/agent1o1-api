<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PipedriveDeleteOrganizationNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_delete_organization';
    }

    public function name(): string
    {
        return 'Pipedrive: Delete Organization';
    }

    public function description(): string
    {
        return 'Deletes a organization. Pipedrive keeps it for 30 days.';
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
                'record_id' => Field::text('Organization id', 'The id from a previous step (a number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The organization');

        $this->pipedrive($run, $config, 'DELETE', "/organizations/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
