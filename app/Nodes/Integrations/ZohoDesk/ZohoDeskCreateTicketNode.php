<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class ZohoDeskCreateTicketNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_create_ticket';
    }

    public function name(): string
    {
        return 'Zoho Desk: Create Ticket';
    }

    public function description(): string
    {
        return 'Opens a ticket for a customer, found by their email or created with it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['department_id', 'subject', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'department_id' => $this->departmentField(),
                'subject' => Field::text('Subject', null, 'Cannot log in'),
                'email' => Field::text('Customer email', null, 'ada@example.com'),
                'contact_name' => Field::advanced(Field::text('Customer name', 'Used when the customer is new.')),
                'description' => Field::textarea('Description'),
                'priority' => Field::advanced(Field::select('Priority', ['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High'])),
                'status' => Field::advanced(Field::text('Status', 'Empty uses Open.')),
                'channel' => Field::advanced(Field::select('Channel', ['Email' => 'Email', 'Web' => 'Web', 'Phone' => 'Phone', 'Chat' => 'Chat', 'Forums' => 'Forums'], null, 'Web')),
                'assignee_id' => $this->agentField(),
                'due_date' => Field::advanced(Field::datetime('Due')),
                    'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By API name, e.g. {"cf_plan": "Gold"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = $this->email($config);
        $name = trim((string) ($config['contact_name'] ?? '')) ?: strstr($email, '@', true);
        $due = filled($config['due_date'] ?? null) ? Carbon::parse((string) $config['due_date'])->utc()->format('Y-m-d\\TH:i:s.v\\Z') : null;

        $ticket = $this->desk($run, $config, 'POST', '/tickets', [], array_filter([
            'departmentId' => $this->numericId($config, 'department_id', 'The department'),
            'subject' => $this->requiredString($config, 'subject', 'The subject'),
            'email' => $email,
            'contact' => ['email' => $email, 'lastName' => $name],
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'priority' => in_array($config['priority'] ?? null, ['Low', 'Medium', 'High'], true) ? $config['priority'] : null,
            'status' => filled($config['status'] ?? null) ? trim((string) $config['status']) : null,
            'channel' => filled($config['channel'] ?? null) ? trim((string) $config['channel']) : 'Web',
            'assigneeId' => $this->optionalId($config, 'assignee_id', 'The agent'),
            'dueDate' => $due,
            'cf' => $this->customFields($config) ?: null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeTicket($ticket);
    }
}
