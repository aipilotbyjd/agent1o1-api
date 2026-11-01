<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotUpdateDealNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_update_deal';
    }

    public function name(): string
    {
        return 'HubSpot: Update Deal';
    }

    public function description(): string
    {
        return 'Changes a deal\'s properties. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Deal id', 'The id from a previous step (a number).', '51'),
                'name' => Field::text('Deal name', null, 'Acme annual plan'),
                'amount' => Field::text('Amount', 'A number, in the account\'s currency.', '4800'),
                'pipeline_id' => Field::advanced(Field::dynamic('Pipeline', 'hubspot.deal_pipelines', 'Empty uses the default pipeline.', 'Sales')),
                'stage_id' => Field::advanced(Field::dynamic('Stage', 'hubspot.deal_stages', null, 'Appointment scheduled', dependsOn: ['pipeline_id'])),
                'close_date' => Field::advanced(Field::datetime('Expected close')),
                'owner_id' => $this->ownerField(),
                'properties' => $this->extraPropertiesField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'record_id', 'The deal');
        $properties = $this->properties($config, ['name' => 'dealname', 'amount' => 'amount', 'pipeline_id' => 'pipeline', 'stage_id' => 'dealstage', 'close_date' => 'closedate', 'owner_id' => 'hubspot_owner_id']);

        if ($properties === []) {
            throw new InvalidArgumentException('Change at least one property.');
        }

        return $this->shapeRecord($this->hubspot($run, $config, 'PATCH', "/crm/v3/objects/deals/{$id}", [], ['properties' => $properties]));
    }
}
