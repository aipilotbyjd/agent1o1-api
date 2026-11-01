<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskCreateContactNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_create_contact';
    }

    public function name(): string
    {
        return 'Zoho Desk: Create Contact';
    }

    public function description(): string
    {
        return 'Adds a customer contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['last_name'],
            'properties' => [
                ...$this->credentialFields(),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'phone' => Field::advanced(Field::text('Phone')),
                'account_id' => Field::advanced(Field::text('Company id', 'A long number, from a previous step.')),
                'description' => Field::advanced(Field::textarea('Notes')),
                    'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By API name, e.g. {"cf_plan": "Gold"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contact = $this->desk($run, $config, 'POST', '/contacts', [], array_filter([
            'firstName' => filled($config['first_name'] ?? null) ? trim((string) $config['first_name']) : null,
            'lastName' => $this->requiredString($config, 'last_name', 'The last name'),
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'phone' => filled($config['phone'] ?? null) ? trim((string) $config['phone']) : null,
            'accountId' => $this->optionalId($config, 'account_id', 'The company'),
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'cf' => $this->customFields($config) ?: null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeContact($contact);
    }
}
