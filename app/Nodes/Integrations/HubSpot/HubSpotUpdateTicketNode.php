<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HubSpotUpdateTicketNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_update_ticket';
    }

    public function name(): string
    {
        return 'HubSpot: Update Ticket';
    }

    public function description(): string
    {
        return 'Changes a ticket\'s properties. Only what is filled in changes.';
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
                'record_id' => Field::text('Ticket id', 'The id from a previous step (a number).', '51'),
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
        $id = $this->numericId($config, 'record_id', 'The ticket');
        $properties = $this->properties($config, ['subject' => 'subject', 'content' => 'content', 'pipeline_id' => 'hs_pipeline', 'stage_id' => 'hs_pipeline_stage', 'priority' => 'hs_ticket_priority', 'owner_id' => 'hubspot_owner_id']);

        if ($properties === []) {
            throw new InvalidArgumentException('Change at least one property.');
        }

        return $this->shapeRecord($this->hubspot($run, $config, 'PATCH', "/crm/v3/objects/tickets/{$id}", [], ['properties' => $properties]));
    }
}
