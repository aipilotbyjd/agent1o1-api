<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class BrevoTrackEventNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_track_event';
    }

    public function name(): string
    {
        return 'Brevo: Track Event';
    }

    public function description(): string
    {
        return 'Records an event for a contact (a purchase, a signup) that Brevo automations can start from.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_name', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'event_name' => Field::text('Event name', null, 'order_placed'),
                'email' => Field::text('Contact email', null, 'ada@example.com'),
                'properties' => Field::json('Event properties (JSON)', 'Details of the event, e.g. {"total": 49}.'),
                'contact_properties' => Field::advanced(Field::json('Contact fields (JSON)', 'Set on the contact too, e.g. {"FIRSTNAME": "Ada"}.')),
                'time' => Field::advanced(Field::datetime('When it happened', 'Leave empty for now.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'event_name', 'The event name');

        $this->brevo($run, $config, 'POST', '/events', [
            'event_name' => $name,
            'event_date' => filled($config['time'] ?? null) ? Carbon::parse((string) $config['time'])->utc()->toIso8601String() : null,
            'identifiers' => ['email_id' => $this->email($config)],
            'contact_properties' => $this->decoded($config['contact_properties'] ?? null, 'The contact fields'),
            'event_properties' => $this->decoded($config['properties'] ?? null, 'The event properties'),
        ]);

        return ['tracked' => true, 'event' => $name, 'email' => $this->email($config)];
    }
}
