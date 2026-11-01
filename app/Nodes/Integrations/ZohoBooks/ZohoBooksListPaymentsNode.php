<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksListPaymentsNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_list_payments';
    }

    public function name(): string
    {
        return 'Zoho Books: List Payments';
    }

    public function description(): string
    {
        return 'Lists the payments received from customers, newest first.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $payments = $this->listAll($run, $config, '/customerpayments', 'customerpayments', array_filter([
            'customer_id' => $this->optionalId($config, 'customer_id', 'The customer'),
            'sort_column' => 'created_time',
            'sort_order' => 'D',
        ], fn (mixed $value): bool => $value !== null), $this->limit($config, 50));

        return ['payments' => array_map($this->shapePayment(...), $payments), 'count' => count($payments)];
    }
}
