<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksCreateBillNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_create_bill';
    }

    public function name(): string
    {
        return 'Zoho Books: Create Bill';
    }

    public function description(): string
    {
        return 'Records a bill from a vendor, from a list of lines that name an account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['vendor_id', 'bill_number', 'line_items'],
            'properties' => [
                ...$this->credentialFields(),
                'vendor_id' => $this->vendorField(),
                'bill_number' => Field::text('Bill number', 'The vendor invoice number.'),
                'line_items' => Field::json('Lines (JSON)', 'A list, e.g. [{"account_id": "460000000012345", "description": "Paper", "rate": 40, "quantity": 5}].'),
                'date' => Field::advanced(Field::datetime('Bill date', 'Empty is today.')),
                'due_date' => Field::advanced(Field::datetime('Due date')),
                'notes' => Field::advanced(Field::textarea('Notes')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $bill = $this->books($run, $config, 'POST', '/bills', [], array_filter([
            'vendor_id' => $this->numericId($config, 'vendor_id', 'The vendor'),
            'bill_number' => $this->requiredString($config, 'bill_number', 'The bill number'),
            'line_items' => $this->lineItems($config),
            'date' => $this->date($config, 'date'),
            'due_date' => $this->date($config, 'due_date'),
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeBill((array) ($bill['bill'] ?? []));
    }
}
