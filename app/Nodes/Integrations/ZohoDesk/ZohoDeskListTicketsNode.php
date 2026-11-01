<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskListTicketsNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_list_tickets';
    }

    public function name(): string
    {
        return 'Zoho Desk: List Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets, newest first, optionally of one department, status or agent.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'department_id' => Field::advanced($this->departmentField()),
                'status' => Field::advanced(Field::text('Status', 'e.g. Open, On Hold, Closed. Several can be separated by commas.')),
                'assignee_id' => Field::advanced($this->agentField('Assigned to')),
                'order' => Field::advanced(Field::select('Newest by', ['createdTime' => 'Created', 'modifiedTime' => 'Last changed'], null, 'createdTime')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $order = ($config['order'] ?? 'createdTime') === 'modifiedTime' ? 'modifiedTime' : 'createdTime';
        $tickets = $this->listAll($run, $config, '/tickets', [
            'sortBy' => '-'.$order,
            'departmentId' => $this->optionalId($config, 'department_id', 'The department'),
            'status' => filled($config['status'] ?? null) ? trim((string) $config['status']) : null,
            'assignee' => $this->optionalId($config, 'assignee_id', 'The agent'),
            'include' => 'contacts,assignee',
        ], $this->limit($config, 50));

        return ['tickets' => array_map($this->shapeTicket(...), $tickets), 'count' => count($tickets)];
    }
}
