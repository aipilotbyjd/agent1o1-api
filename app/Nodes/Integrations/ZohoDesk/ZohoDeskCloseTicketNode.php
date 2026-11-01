<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskCloseTicketNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_close_ticket';
    }

    public function name(): string
    {
        return 'Zoho Desk: Close Ticket';
    }

    public function description(): string
    {
        return 'Sets a ticket\'s status to Closed.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $ticket = $this->desk($run, $config, 'PATCH', "/tickets/{$id}", [], ['status' => 'Closed']);

        return ['closed' => true, 'id' => $id, 'status' => $ticket['status'] ?? 'Closed'];
    }
}
