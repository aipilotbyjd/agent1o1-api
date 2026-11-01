<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskGetTicketNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_get_ticket';
    }

    public function name(): string
    {
        return 'Zoho Desk: Get Ticket';
    }

    public function description(): string
    {
        return 'Reads a ticket with its contact and agent. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $response = $this->deskResponse($run, $config, 'GET', "/tickets/{$id}", ['include' => 'contacts,assignee']);

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeTicket((array) $response->json()), 'found' => true];
    }
}
