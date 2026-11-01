<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksMarkInvoiceNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_mark_invoice';
    }

    public function name(): string
    {
        return 'Zoho Books: Mark Invoice Sent or Void';
    }

    public function description(): string
    {
        return 'Marks a draft invoice as sent without emailing it, or voids an invoice.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['invoice_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                'invoice_id' => Field::text('Invoice id', 'An id from a previous step (a long number).'),
                'status' => Field::select('Mark as', ['sent' => 'Sent', 'void' => 'Void'], null, 'sent'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'invoice_id', 'The invoice');
        $status = ($config['status'] ?? 'sent') === 'void' ? 'void' : 'sent';

        $this->books($run, $config, 'POST', "/invoices/{$id}/status/{$status}");

        return ['id' => $id, 'status' => $status];
    }
}
