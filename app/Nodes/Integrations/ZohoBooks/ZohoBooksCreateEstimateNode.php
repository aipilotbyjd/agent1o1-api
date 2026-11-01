<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksCreateEstimateNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_create_estimate';
    }

    public function name(): string
    {
        return 'Zoho Books: Create Estimate';
    }

    public function description(): string
    {
        return 'Creates a draft estimate (quote) for a customer from a list of lines.';
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
                'date' => Field::advanced(Field::datetime('Estimate date', 'Empty is today.')),
                'expiry_date' => Field::advanced(Field::datetime('Valid until')),
                'reference' => Field::advanced(Field::text('Reference')),
                'notes' => Field::advanced(Field::textarea('Notes shown on the estimate')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $estimate = $this->books($run, $config, 'POST', '/estimates', [], array_filter([
            'customer_id' => $this->numericId($config, 'customer_id', 'The customer'),
            'line_items' => $this->lineItems($config),
            'date' => $this->date($config, 'date'),
            'expiry_date' => $this->date($config, 'expiry_date'),
            'reference_number' => filled($config['reference'] ?? null) ? trim((string) $config['reference']) : null,
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeEstimate((array) ($estimate['estimate'] ?? []));
    }
}
