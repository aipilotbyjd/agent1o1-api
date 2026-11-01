<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksListBillsNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_list_bills';
    }

    public function name(): string
    {
        return 'Zoho Books: List Bills';
    }

    public function description(): string
    {
        return 'Lists bills from vendors, newest first.';
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
                'vendor_id' => Field::advanced($this->vendorField()),
                'status' => Field::advanced(Field::select('Status', ['draft' => 'Draft', 'open' => 'Open', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void', 'unpaid' => 'Unpaid', 'partially_paid' => 'Partially paid'])),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $bills = $this->listAll($run, $config, '/bills', 'bills', array_filter([
            'vendor_id' => $this->optionalId($config, 'vendor_id', 'The vendor'),
            'status' => in_array($config['status'] ?? null, ['draft', 'open', 'overdue', 'paid', 'void', 'unpaid', 'partially_paid'], true) ? $config['status'] : null,
            'sort_column' => 'created_time',
            'sort_order' => 'D',
        ], fn (mixed $value): bool => $value !== null), $this->limit($config, 50));

        return ['bills' => array_map($this->shapeBill(...), $bills), 'count' => count($bills)];
    }
}
