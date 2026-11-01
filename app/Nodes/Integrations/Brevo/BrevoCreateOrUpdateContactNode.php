<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class BrevoCreateOrUpdateContactNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_create_or_update_contact';
    }

    public function name(): string
    {
        return 'Brevo: Add or Update Contact';
    }

    public function description(): string
    {
        return 'Adds a contact, or updates the one with that email, and puts them on some lists. Safe to run twice.';
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
                'phone' => Field::advanced(Field::text('Phone', 'International format, e.g. +14155550123.')),
                'attributes' => Field::advanced(Field::json('Other contact fields (JSON)', 'Your own fields by name, e.g. {"COMPANY": "Acme"}.')),

                'list_ids' => Field::dynamic('Add to lists', 'brevo.lists', 'Put them on these lists.', 'Newsletter', multiple: true, type: 'array'),
                'email_blocked' => Field::advanced(Field::select('Block emails', ['unchanged' => 'Leave as it is', 'yes' => 'Block', 'no' => 'Allow'], 'Blocking is Brevo\'s unsubscribe.', 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = $this->email($config);
        $blocked = (string) ($config['email_blocked'] ?? 'unchanged');
        $attributes = $this->attributes($config);

        $created = $this->brevo($run, $config, 'POST', '/contacts', [
            'email' => $email,
            'attributes' => $attributes === [] ? null : $attributes,
            'listIds' => $this->ids($config['list_ids'] ?? null) ?: null,
            'updateEnabled' => true,
            'emailBlacklisted' => $blocked === 'yes' ? true : ($blocked === 'no' ? false : null),
        ]);

        // Brevo answers 201 with the new id, or 204 for a contact that was only updated.
        return [...$this->shapeContact((array) $this->brevo($run, $config, 'GET', '/contacts/'.rawurlencode($email))), 'created' => is_array($created) && isset($created['id'])];
    }
}
