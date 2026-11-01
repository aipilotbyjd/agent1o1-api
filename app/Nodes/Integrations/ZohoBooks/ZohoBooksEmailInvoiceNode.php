<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksEmailInvoiceNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_email_invoice';
    }

    public function name(): string
    {
        return 'Zoho Books: Email Invoice';
    }

    public function description(): string
    {
        return 'Emails an invoice to the customer, or to the addresses given.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['invoice_id'],
            'properties' => [
                ...$this->credentialFields(),
                'invoice_id' => Field::text('Invoice id', 'An id from a previous step (a long number).'),
                'to' => Field::advanced(Field::emails('Send to', 'Empty uses the customer contacts.')),
                'subject' => Field::advanced(Field::text('Subject')),
                'body' => Field::advanced(Field::textarea('Message')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'invoice_id', 'The invoice');
        $to = array_values(array_filter(array_map(fn (mixed $address): string => $this->email(['email' => $address]), is_array($config['to'] ?? null) ? $config['to'] : preg_split('/[\s,;]+/', (string) ($config['to'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))));

        $this->books($run, $config, 'POST', "/invoices/{$id}/email", [], array_filter([
            'to_mail_ids' => $to ?: null,
            'subject' => filled($config['subject'] ?? null) ? (string) $config['subject'] : null,
            'body' => filled($config['body'] ?? null) ? (string) $config['body'] : null,
        ], fn (mixed $value): bool => $value !== null));

        return ['sent' => true, 'id' => $id, 'to' => $to];
    }
}
