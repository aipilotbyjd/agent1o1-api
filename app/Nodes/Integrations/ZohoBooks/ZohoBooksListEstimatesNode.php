<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksListEstimatesNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_list_estimates';
    }

    public function name(): string
    {
        return 'Zoho Books: List Estimates';
    }

    public function description(): string
    {
        return 'Lists estimates (quotes), newest first.';
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
                'status' => Field::advanced(Field::select('Status', ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'invoiced' => 'Invoiced', 'expired' => 'Expired'])),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $estimates = $this->listAll($run, $config, '/estimates', 'estimates', array_filter([
            'customer_id' => $this->optionalId($config, 'customer_id', 'The customer'),
            'status' => in_array($config['status'] ?? null, ['draft', 'sent', 'accepted', 'declined', 'invoiced', 'expired'], true) ? $config['status'] : null,
            'sort_column' => 'created_time',
            'sort_order' => 'D',
        ], fn (mixed $value): bool => $value !== null), $this->limit($config, 50));

        return ['estimates' => array_map($this->shapeEstimate(...), $estimates), 'count' => count($estimates)];
    }
}
