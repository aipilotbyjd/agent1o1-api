<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksCreateVendorNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_create_vendor';
    }

    public function name(): string
    {
        return 'Zoho Books: Create Vendor';
    }

    public function description(): string
    {
        return 'Adds a vendor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', 'The name shown on documents.', 'Acme Inc'),
                'company' => Field::text('Company'),
                'email' => Field::text('Email', null, 'billing@acme.com'),
                'first_name' => Field::advanced(Field::text('Contact first name')),
                'last_name' => Field::advanced(Field::text('Contact last name')),
                'phone' => Field::advanced(Field::text('Phone')),
                'currency_id' => Field::advanced(Field::text('Currency id', 'A long number. Empty uses the organization currency.')),
                'notes' => Field::advanced(Field::textarea('Notes')),
                'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'A list of label/value pairs, e.g. [{"customfield_id": "460000000012345", "value": "Gold"}].')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = filled($config['email'] ?? null) ? $this->email($config) : null;
        $person = array_filter(['first_name' => $config['first_name'] ?? null, 'last_name' => $config['last_name'] ?? null, 'email' => $email, 'phone' => $config['phone'] ?? null, 'is_primary_contact' => true], fn (mixed $value): bool => filled($value));

        $contact = $this->books($run, $config, 'POST', '/contacts', [], array_filter([
            'contact_name' => $this->requiredString($config, 'name', 'The name'),
            'company_name' => filled($config['company'] ?? null) ? trim((string) $config['company']) : null,
            'contact_type' => 'vendor',
            'contact_persons' => count($person) > 1 ? [$person] : null,
            'currency_id' => $this->optionalId($config, 'currency_id', 'The currency'),
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
            'custom_fields' => $this->customFieldList($config) ?: null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->shapeContact((array) ($contact['contact'] ?? []));
    }
}
