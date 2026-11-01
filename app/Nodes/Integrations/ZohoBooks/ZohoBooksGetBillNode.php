<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksGetBillNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_get_bill';
    }

    public function name(): string
    {
        return 'Zoho Books: Get Bill';
    }

    public function description(): string
    {
        return 'Reads a bill. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['bill_id'],
            'properties' => [
                ...$this->credentialFields(),
                'bill_id' => Field::text('Bill id', 'An id from a previous step (a long number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'bill_id', 'The bill');
        $response = $this->booksResponse($run, $config, 'GET', "/bills/{$id}");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeBill((array) $response->json('bill')), 'found' => true];
    }
}
