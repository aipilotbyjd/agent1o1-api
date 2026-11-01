<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HubSpotCreateTaskNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_create_task';
    }

    public function name(): string
    {
        return 'HubSpot: Create Task';
    }

    public function description(): string
    {
        return 'Creates a to-do, linked to a contact, company, deal or ticket, for someone to do.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object', 'record_id', 'subject'],
            'properties' => [
                ...$this->credentialFields(),
                'object' => Field::select('For', ['contacts' => 'Contact', 'companies' => 'Company', 'deals' => 'Deal', 'tickets' => 'Ticket'], null, 'contacts'),
                'record_id' => Field::text('Record id', 'The id from a previous step (a number).'),
                'subject' => Field::text('Title', null, 'Call back about the quote'),
                'body' => Field::textarea('Notes'),
                'due' => Field::advanced(Field::datetime('Due', 'Empty is now.')),
                'priority' => Field::advanced(Field::select('Priority', ['NONE' => 'None', 'LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High'], null, 'NONE')),
                'owner_id' => $this->ownerField('Assign to'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $object = $this->objectOf($config);
        $id = $this->numericId($config, 'record_id', 'The record');
        $priority = in_array($config['priority'] ?? 'NONE', ['NONE', 'LOW', 'MEDIUM', 'HIGH'], true) ? ($config['priority'] ?? 'NONE') : 'NONE';
        $task = $this->hubspot($run, $config, 'POST', '/crm/v3/objects/tasks', [], [
            'properties' => array_filter([
                'hs_task_subject' => $this->requiredString($config, 'subject', 'The title'),
                'hs_task_body' => filled($config['body'] ?? null) ? (string) $config['body'] : null,
                'hs_timestamp' => $this->date($config, 'due') ?? now()->utc()->format('Y-m-d\\TH:i:s.v\\Z'),
                'hs_task_status' => 'NOT_STARTED',
                'hs_task_priority' => $priority === 'NONE' ? null : $priority,
                'hubspot_owner_id' => filled($config['owner_id'] ?? null) ? $this->numericId($config, 'owner_id', 'The owner') : null,
            ], fn (mixed $value): bool => $value !== null),
            'associations' => [['to' => ['id' => $id], 'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => self::OBJECTS[$object]['task']]]]],
        ]);

        return ['id' => isset($task['id']) ? (string) $task['id'] : null, 'object' => $object, 'record_id' => $id];
    }
}
