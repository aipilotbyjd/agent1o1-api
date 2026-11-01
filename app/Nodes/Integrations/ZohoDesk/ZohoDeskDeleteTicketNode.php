<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskDeleteTicketNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_delete_ticket';
    }

    public function name(): string
    {
        return 'Zoho Desk: Move Ticket to Trash';
    }

    public function description(): string
    {
        return 'Moves a ticket to the trash, where Zoho Desk keeps it for 30 days.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        $this->desk($run, $config, 'POST', '/tickets/moveToTrash', [], ['ticketIds' => [$id]]);

        return ['trashed' => true, 'id' => $id];
    }
}
