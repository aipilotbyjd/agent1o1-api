<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksListInvoicesNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_list_invoices';
    }

    public function name(): string
    {
        return 'Zoho Books: List Invoices';
    }

    public function description(): string
    {
        return 'Lists invoices, newest first, optionally of one customer or status.';
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
                'customer_id' => Field::advanced($this->customerField()),
                'status' => Field::advanced(Field::select('Status', ['draft' => 'Draft', 'sent' => 'Sent', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void', 'unpaid' => 'Unpaid', 'partially_paid' => 'Partially paid'])),
                'search' => Field::advanced(Field::text('Search', 'Number, reference or customer name.')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $invoices = $this->listAll($run, $config, '/invoices', 'invoices', array_filter([
            'customer_id' => $this->optionalId($config, 'customer_id', 'The customer'),
            'status' => in_array($config['status'] ?? null, ['draft', 'sent', 'overdue', 'paid', 'void', 'unpaid', 'partially_paid'], true) ? $config['status'] : null,
            'search_text' => filled($config['search'] ?? null) ? trim((string) $config['search']) : null,
            'sort_column' => 'created_time',
            'sort_order' => 'D',
        ], fn (mixed $value): bool => $value !== null), $this->limit($config, 50));

        return ['invoices' => array_map($this->shapeInvoice(...), $invoices), 'count' => count($invoices)];
    }
}
