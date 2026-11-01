<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SendGridUpsertContactNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_upsert_contact';
    }

    public function name(): string
    {
        return 'SendGrid: Add or Update Contact';
    }

    public function description(): string
    {
        return 'Adds a marketing contact, or updates the one with that email, and puts them on lists. SendGrid does this in the background, so the contact can take a minute to show.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'phone' => Field::advanced(Field::text('Phone')),
                'city' => Field::advanced(Field::text('City')),
                'country' => Field::advanced(Field::text('Country')),
                'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By field id, e.g. {"e1_T": "Acme"}. Ids are in SendGrid > Marketing > Custom Fields.')),
                'list_ids' => Field::dynamic('Add to lists', 'sendgrid.lists', null, 'Newsletter', multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = $this->email($config);
        $listIds = array_values(array_filter(array_map('trim', is_array($config['list_ids'] ?? null) ? $config['list_ids'] : explode(',', (string) ($config['list_ids'] ?? '')))));

        $result = (array) $this->sendgrid($run, $config, 'PUT', '/marketing/contacts', [
            'list_ids' => $listIds === [] ? null : $listIds,
            'contacts' => [$this->withoutNulls([
                'email' => $email,
                'first_name' => filled($config['first_name'] ?? null) ? (string) $config['first_name'] : null,
                'last_name' => filled($config['last_name'] ?? null) ? (string) $config['last_name'] : null,
                'phone_number' => filled($config['phone'] ?? null) ? (string) $config['phone'] : null,
                'city' => filled($config['city'] ?? null) ? (string) $config['city'] : null,
                'country' => filled($config['country'] ?? null) ? (string) $config['country'] : null,
                'custom_fields' => $this->decoded($config['custom_fields'] ?? null, 'The custom fields'),
            ])],
        ]);

        return ['queued' => true, 'job_id' => $result['job_id'] ?? null, 'email' => $email];
    }
}
