<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class BrevoUpdateContactNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_update_contact';
    }

    public function name(): string
    {
        return 'Brevo: Update Contact';
    }

    public function description(): string
    {
        return 'Changes a contact\'s details and lists. Only what is filled in changes. Fails if they do not exist.';
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

                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'phone' => Field::advanced(Field::text('Phone', 'International format, e.g. +14155550123.')),
                'attributes' => Field::advanced(Field::json('Other contact fields (JSON)', 'Your own fields by name, e.g. {"COMPANY": "Acme"}.')),

                'add_to_lists' => Field::dynamic('Add to lists', 'brevo.lists', null, 'Newsletter', multiple: true, type: 'array'),
                'remove_from_lists' => Field::dynamic('Remove from lists', 'brevo.lists', null, 'Newsletter', multiple: true, type: 'array'),
                'email_blocked' => Field::advanced(Field::select('Block emails', ['unchanged' => 'Leave as it is', 'yes' => 'Block', 'no' => 'Allow'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = $this->contactPath($config);
        $blocked = (string) ($config['email_blocked'] ?? 'unchanged');
        $attributes = $this->attributes($config);
        $body = [
            'attributes' => $attributes === [] ? null : $attributes,
            'listIds' => $this->ids($config['add_to_lists'] ?? null) ?: null,
            'unlinkListIds' => $this->ids($config['remove_from_lists'] ?? null) ?: null,
            'emailBlacklisted' => $blocked === 'yes' ? true : ($blocked === 'no' ? false : null),
        ];

        if (array_filter($body, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        $this->brevo($run, $config, 'PUT', $path, $body);

        return $this->shapeContact((array) $this->brevo($run, $config, 'GET', $path));
    }
}
