<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotCreateTicketNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_create_ticket';
    }

    public function name(): string
    {
        return 'HubSpot: Create Ticket';
    }

    public function description(): string
    {
        return 'Creates a ticket. Properties not in the fields below go in the JSON map.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subject'],
            'properties' => [
                ...$this->credentialFields(),
                'subject' => Field::text('Subject', null, 'Cannot log in'),
                'content' => Field::textarea('Description'),
                'pipeline_id' => Field::advanced(Field::dynamic('Pipeline', 'hubspot.ticket_pipelines', null, 'Support')),
                'stage_id' => Field::advanced(Field::dynamic('Stage', 'hubspot.ticket_stages', 'Required by HubSpot to create a ticket: the first stage of the pipeline.', 'New', dependsOn: ['pipeline_id'])),
                'priority' => Field::advanced(Field::select('Priority', ['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High'])),
                'owner_id' => $this->ownerField(),
                'properties' => $this->extraPropertiesField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $properties = $this->properties($config, ['subject' => 'subject', 'content' => 'content', 'pipeline_id' => 'hs_pipeline', 'stage_id' => 'hs_pipeline_stage', 'priority' => 'hs_ticket_priority', 'owner_id' => 'hubspot_owner_id']);

        if ($properties === []) {
            throw new InvalidArgumentException('Give at least one property for the ticket.');
        }

        $record = $this->hubspot($run, $config, 'POST', '/crm/v3/objects/tickets', [], ['properties' => $properties]);

        return $this->shapeRecord($record);
    }
}
