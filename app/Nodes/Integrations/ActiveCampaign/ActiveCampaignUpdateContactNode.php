<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ActiveCampaignUpdateContactNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_update_contact';
    }

    public function name(): string
    {
        return 'ActiveCampaign: Update Contact';
    }

    public function description(): string
    {
        return 'Changes a contact\'s email, name, phone or custom fields. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact'],
            'properties' => [
                ...$this->credentialFields(),
                'contact' => $this->contactField(),
                'new_email' => Field::text('New email'),

                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'phone' => Field::advanced(Field::text('Phone')),
                'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'Field id => value, e.g. {"3": "Acme"}. See List Custom Fields for the ids.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->contactId($run, $config);
        $fields = $this->fieldValues($config);
        $body = array_filter([
            'email' => filled($config['new_email'] ?? null) ? $this->email($config, 'new_email') : null,
            'firstName' => filled($config['first_name'] ?? null) ? (string) $config['first_name'] : null,
            'lastName' => filled($config['last_name'] ?? null) ? (string) $config['last_name'] : null,
            'phone' => filled($config['phone'] ?? null) ? (string) $config['phone'] : null,
            'fieldValues' => $fields ?: null,
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        $result = (array) $this->ac($run, $config, 'PUT', "/contacts/{$id}", ['contact' => $body]);

        return $this->shapeContact((array) ($result['contact'] ?? []), (array) ($result['fieldValues'] ?? []));
    }
}
