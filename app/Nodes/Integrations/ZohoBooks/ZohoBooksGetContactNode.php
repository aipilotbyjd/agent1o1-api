<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksGetContactNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_get_contact';
    }

    public function name(): string
    {
        return 'Zoho Books: Get Contact';
    }

    public function description(): string
    {
        return 'Reads a customer or vendor. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id', 'An id from a previous step (a long number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'contact_id', 'The contact');
        $response = $this->booksResponse($run, $config, 'GET', "/contacts/{$id}");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return [...$this->shapeContact((array) $response->json('contact')), 'found' => true];
    }
}
