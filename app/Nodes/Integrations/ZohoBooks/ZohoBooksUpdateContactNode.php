<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoBooksUpdateContactNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_update_contact';
    }

    public function name(): string
    {
        return 'Zoho Books: Update Contact';
    }

    public function description(): string
    {
        return 'Changes a customer or vendor. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id', 'An id from a previous step (a long number).'),
                'name' => Field::text('Name'),
                'company' => Field::text('Company'),
                'notes' => Field::advanced(Field::textarea('Notes')),
                'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'A list of label/value pairs, e.g. [{"customfield_id": "460000000012345", "value": "Gold"}].')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'contact_id', 'The contact');
        $body = array_filter([
            'contact_name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'company_name' => filled($config['company'] ?? null) ? trim((string) $config['company']) : null,
            'notes' => filled($config['notes'] ?? null) ? (string) $config['notes'] : null,
            'custom_fields' => $this->customFieldList($config) ?: null,
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeContact((array) ($this->books($run, $config, 'PUT', "/contacts/{$id}", [], $body)['contact'] ?? []));
    }
}
