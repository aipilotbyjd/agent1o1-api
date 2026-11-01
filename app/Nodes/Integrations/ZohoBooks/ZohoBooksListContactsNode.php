<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoBooksListContactsNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_list_contacts';
    }

    public function name(): string
    {
        return 'Zoho Books: List Contacts';
    }

    public function description(): string
    {
        return 'Lists customers and vendors, newest first, or finds one by email or name.';
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
                'type' => Field::select('Type', ['customer' => 'Customers', 'vendor' => 'Vendors'], null, 'customer'),
                'email' => Field::advanced(Field::text('Email')),
                'search' => Field::advanced(Field::text('Name contains')),
                'status' => Field::advanced(Field::select('Status', ['Status.Active' => 'Active', 'Status.Inactive' => 'Inactive', 'Status.All' => 'All'], null, 'Status.Active')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contacts = $this->listAll($run, $config, '/contacts', 'contacts', array_filter([
            'contact_type' => ($config['type'] ?? 'customer') === 'vendor' ? 'vendor' : 'customer',
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'contact_name_contains' => filled($config['search'] ?? null) ? trim((string) $config['search']) : null,
            'filter_by' => in_array($config['status'] ?? null, ['Status.Active', 'Status.Inactive', 'Status.All'], true) ? $config['status'] : 'Status.Active',
            'sort_column' => 'created_time',
            'sort_order' => 'D',
        ], fn (mixed $value): bool => $value !== null), $this->limit($config, 50));

        return ['contacts' => array_map($this->shapeContact(...), $contacts), 'count' => count($contacts)];
    }
}
