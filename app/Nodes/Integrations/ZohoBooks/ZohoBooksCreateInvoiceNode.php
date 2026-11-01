<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksCreateInvoiceNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_create_invoice';
    }

    public function name(): string
    {
        return 'Zoho Books: Create Invoice';
    }

    public function description(): string
    {
        return 'Creates a draft invoice for a customer from a list of lines.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['customer_id', 'line_items'],
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => $this->customerField(),
                'line_items' => Field::json('Lines (JSON)', 'A list, e.g. [{"name": "Consulting", "rate": 120, "quantity": 2}]. Use item_id to bill a saved item.'),
                'date' => Field::advanced(Field::datetime('Invoice date', 'Empty is today.')),
                'due_date' => Field::advanced(Field::datetime('Due date')),
                'reference' => Field::advanced(Field::text('Reference')),
                'notes' => Field::advanced(Field::textarea('Notes shown on the invoice')),
                'terms' => Field::advanced(Field::textarea('Terms')),
                'send' => Field::advanced(Field::boolean('Email it to the customer right away', null, false)),
                'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'A list of label/value pairs, e.g. [{"customfield_id": "460000000012345", "value": "Gold"}].')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $invoice = $this->books($run, $config, 'POST', '/invoices', ['send' => ($config['send'] ?? false) ? 'true' : null], array_filter([
            'customer_id' => $this->numericId($config, 'customer_id', 'The customer'),
            'line_items' => $this->lineItems($config),
            'date' => $this->date($config, 'date'),
            'due_date' => $this->date($config, 'due_date'),
            'reference_number' => filled($config['reference'] ?? null) ? trim((string) $config['reference']) : null,
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
            'terms' => filled($config['terms'] ?? null) ? (string) $config['terms'] : null,
            'custom_fields' => $this->customFieldList($config) ?: null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeInvoice((array) ($invoice['invoice'] ?? []));
    }
}
