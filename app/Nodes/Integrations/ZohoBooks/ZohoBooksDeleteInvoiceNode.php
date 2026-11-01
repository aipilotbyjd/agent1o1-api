<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksDeleteInvoiceNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_delete_invoice';
    }

    public function name(): string
    {
        return 'Zoho Books: Delete Invoice';
    }

    public function description(): string
    {
        return 'Deletes an invoice that has no payments. Voiding keeps the record.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $response = $this->booksResponse($run, $config, 'DELETE', "/invoices/{$id}");

        if ($response->status() === 404) {
            return ['deleted' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return ['deleted' => true, 'id' => $id];
    }
}
