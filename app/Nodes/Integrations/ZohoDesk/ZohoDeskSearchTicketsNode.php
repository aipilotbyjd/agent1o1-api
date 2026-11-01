<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoDeskSearchTicketsNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_search_tickets';
    }

    public function name(): string
    {
        return 'Zoho Desk: Search Tickets';
    }

    public function description(): string
    {
        return 'Finds tickets by words in the subject, a customer\'s email, a status or a ticket number.';
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
                'subject' => Field::text('Subject contains'),
                'email' => Field::advanced(Field::text('Customer email')),
                'status' => Field::advanced(Field::text('Status')),
                'number' => Field::advanced(Field::text('Ticket number')),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = array_filter([
            'subject' => filled($config['subject'] ?? null) ? trim((string) $config['subject']) : null,
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'status' => filled($config['status'] ?? null) ? trim((string) $config['status']) : null,
            'ticketNumber' => filled($config['number'] ?? null) ? trim((string) $config['number']) : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($query === []) {
            throw new InvalidArgumentException('Give at least one thing to search by.');
        }

        $tickets = $this->listAll($run, $config, '/tickets/search', $query, $this->limit($config, 25, 100));

        return ['tickets' => array_map($this->shapeTicket(...), $tickets), 'count' => count($tickets)];
    }
}
