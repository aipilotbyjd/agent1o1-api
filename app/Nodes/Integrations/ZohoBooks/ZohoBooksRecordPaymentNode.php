<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ZohoBooksRecordPaymentNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_record_payment';
    }

    public function name(): string
    {
        return 'Zoho Books: Record Payment';
    }

    public function description(): string
    {
        return 'Records a payment from a customer, optionally against an invoice.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['customer_id', 'amount'],
            'properties' => [
                ...$this->credentialFields(),
                'customer_id' => $this->customerField(),
                'amount' => Field::text('Amount', null, '120.00'),
                'invoice_id' => Field::advanced(Field::text('Apply to invoice', 'An invoice id. Empty keeps the payment unapplied.')),
                'mode' => Field::advanced(Field::select('Payment mode', ['cash' => 'Cash', 'check' => 'Check', 'creditcard' => 'Credit card', 'banktransfer' => 'Bank transfer', 'bankremittance' => 'Bank remittance', 'autotransaction' => 'Auto transaction', 'others' => 'Others'], null, 'banktransfer')),
                'date' => Field::advanced(Field::datetime('Payment date', 'Empty is today.')),
                'reference' => Field::advanced(Field::text('Reference')),
                'description' => Field::advanced(Field::textarea('Notes')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $amount = (string) ($config['amount'] ?? '');

        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException('The amount must be a number above zero.');
        }

        $invoiceId = $this->optionalId($config, 'invoice_id', 'The invoice');
        $payment = $this->books($run, $config, 'POST', '/customerpayments', [], array_filter([
            'customer_id' => $this->numericId($config, 'customer_id', 'The customer'),
            'payment_mode' => in_array($config['mode'] ?? null, ['cash', 'check', 'creditcard', 'banktransfer', 'bankremittance', 'autotransaction', 'others'], true) ? $config['mode'] : 'banktransfer',
            'amount' => (float) $amount,
            'date' => $this->date($config, 'date') ?? Carbon::now()->format('Y-m-d'),
            'reference_number' => filled($config['reference'] ?? null) ? trim((string) $config['reference']) : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'invoices' => $invoiceId === null ? null : [['invoice_id' => $invoiceId, 'amount_applied' => (float) $amount]],
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapePayment((array) ($payment['payment'] ?? []));
    }
}
