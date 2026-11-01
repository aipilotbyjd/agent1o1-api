<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskFindContactNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_find_contact';
    }

    public function name(): string
    {
        return 'Zoho Desk: Find Contact by Email';
    }

    public function description(): string
    {
        return 'Finds a contact by email address. Says whether one was found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->deskResponse($run, $config, 'GET', '/contacts/search', ['email' => $this->email($config), 'limit' => 1]);

        if ($response->status() !== 204) {
            $this->assertOk($response);
        }

        $contact = (array) ($response->json('data.0') ?? []);

        return $contact === [] ? ['found' => false] : [...$this->shapeContact($contact), 'found' => true];
    }
}
