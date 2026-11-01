<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitCreateOrUpdateSubscriberNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_create_or_update_subscriber';
    }

    public function name(): string
    {
        return 'Kit: Add or Update Subscriber';
    }

    public function description(): string
    {
        return 'Adds a subscriber, or updates their first name and fields if the email is already there. Safe to run twice.';
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
                'fields' => Field::advanced(Field::json('Custom fields (JSON)', 'Your own subscriber fields by name, e.g. {"company": "Acme"}.')),
                'state' => Field::advanced(Field::select('State', ['active' => 'Active (no confirmation email)', 'inactive' => 'Waiting to confirm'], 'Active adds them straight away; waiting asks them to confirm first where your account does that.', 'inactive')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $state = (string) ($config['state'] ?? 'inactive');
        $subscriber = (array) data_get((array) $this->kit($run, $config, 'POST', '/subscribers', [
            'email_address' => $this->email($config),
            'first_name' => filled($config['first_name'] ?? null) ? (string) $config['first_name'] : null,
            'state' => in_array($state, ['active', 'inactive'], true) ? $state : null,
            'fields' => $this->decoded($config['fields'] ?? null, 'The custom fields'),
        ]), 'subscriber', []);

        return $this->shapeSubscriber($subscriber);
    }
}
