<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoDeskUpdateContactNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_update_contact';
    }

    public function name(): string
    {
        return 'Zoho Desk: Update Contact';
    }

    public function description(): string
    {
        return 'Changes a contact. Only what is filled in changes.';
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
                'contact_id' => Field::text('Contact id', 'A long number, from a previous step.'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'email' => Field::text('Email'),
                'phone' => Field::advanced(Field::text('Phone')),
                    'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By API name, e.g. {"cf_plan": "Gold"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'contact_id', 'The contact');
        $body = array_filter([
            'firstName' => filled($config['first_name'] ?? null) ? trim((string) $config['first_name']) : null,
            'lastName' => filled($config['last_name'] ?? null) ? trim((string) $config['last_name']) : null,
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'phone' => filled($config['phone'] ?? null) ? trim((string) $config['phone']) : null,
            'cf' => $this->customFields($config) ?: null,
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeContact($this->desk($run, $config, 'PATCH', "/contacts/{$id}", [], $body));
    }
}
