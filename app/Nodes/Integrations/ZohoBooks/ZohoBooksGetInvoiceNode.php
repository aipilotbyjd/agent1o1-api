<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksGetInvoiceNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_get_invoice';
    }

    public function name(): string
    {
        return 'Zoho Books: Get Invoice';
    }

    public function description(): string
    {
        return 'Reads an invoice with its lines. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['invoice_id'],
            'properties' => [
                ...$this->credentialFields(),
                'invoice_id' => Field::text('Invoice id', 'An id from a previous step (a long number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'invoice_id', 'The invoice');
        $response = $this->booksResponse($run, $config, 'GET', "/invoices/{$id}");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);
        $invoice = (array) $response->json('invoice');

        return [...$this->shapeInvoice($invoice), 'lines' => array_map(fn (array $line): array => ['item_id' => $line['item_id'] ?? null, 'name' => $line['name'] ?? null, 'description' => $line['description'] ?? null, 'rate' => $line['rate'] ?? null, 'quantity' => $line['quantity'] ?? null, 'total' => $line['item_total'] ?? null], array_values(array_filter((array) ($invoice['line_items'] ?? []), 'is_array'))), 'found' => true];
    }
}
