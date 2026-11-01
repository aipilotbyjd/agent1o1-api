<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoDeskUpdateTicketNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_update_ticket';
    }

    public function name(): string
    {
        return 'Zoho Desk: Update Ticket';
    }

    public function description(): string
    {
        return 'Changes a ticket\'s subject, status, priority, department or agent. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id', 'An id from a previous step (a long number).'),
                'subject' => Field::text('Subject'),
                'status' => Field::text('Status', 'e.g. Open, On Hold, Closed.'),
                'priority' => Field::select('Priority', ['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High']),
                'department_id' => Field::advanced($this->departmentField()),
                'assignee_id' => $this->agentField(),
                'description' => Field::advanced(Field::textarea('Description')),
                    'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By API name, e.g. {"cf_plan": "Gold"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $body = array_filter([
            'subject' => filled($config['subject'] ?? null) ? trim((string) $config['subject']) : null,
            'status' => filled($config['status'] ?? null) ? trim((string) $config['status']) : null,
            'priority' => in_array($config['priority'] ?? null, ['Low', 'Medium', 'High'], true) ? $config['priority'] : null,
            'departmentId' => $this->optionalId($config, 'department_id', 'The department'),
            'assigneeId' => $this->optionalId($config, 'assignee_id', 'The agent'),
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'cf' => $this->customFields($config) ?: null,
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeTicket($this->desk($run, $config, 'PATCH', "/tickets/{$id}", [], $body));
    }
}
