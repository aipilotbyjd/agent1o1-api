<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotCreateDealNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_create_deal';
    }

    public function name(): string
    {
        return 'HubSpot: Create Deal';
    }

    public function description(): string
    {
        return 'Creates a deal. Properties not in the fields below go in the JSON map.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
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
        $properties = $this->properties($config, ['name' => 'dealname', 'amount' => 'amount', 'pipeline_id' => 'pipeline', 'stage_id' => 'dealstage', 'close_date' => 'closedate', 'owner_id' => 'hubspot_owner_id']);

        if ($properties === []) {
            throw new InvalidArgumentException('Give at least one property for the deal.');
        }

        $record = $this->hubspot($run, $config, 'POST', '/crm/v3/objects/deals', [], ['properties' => $properties]);

        return $this->shapeRecord($record);
    }
}
