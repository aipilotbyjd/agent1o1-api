<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class KlaviyoTrackEventNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_track_event';
    }

    public function name(): string
    {
        return 'Klaviyo: Track Event';
    }

    public function description(): string
    {
        return 'Records an event for a person (Placed Order, Viewed Product, anything you name), creating the profile if needed. Events can start Klaviyo flows and build segments.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['metric_name', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'metric_name' => Field::text('Event name', 'The metric, e.g. Placed Order. It is created if new.', 'Placed Order'),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'properties' => Field::json('Event properties (JSON)', 'Details of the event, e.g. {"order_id": "1001", "items": ["Shirt"]}.'),
                'value' => Field::text('Value', 'A number, e.g. the order total.', '49.00'),
                'currency' => Field::advanced(Field::text('Currency', 'Three-letter code.', 'USD')),
                'time' => Field::advanced(Field::datetime('When it happened', 'Leave empty for now.')),
                'unique_id' => Field::advanced(Field::text('Unique id', 'A repeat with the same id is not recorded twice, e.g. the order id.')),
                'profile_properties' => Field::advanced(Field::json('Profile properties (JSON)', 'Set on the person too, e.g. {"first_name": "Ada"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $value = $config['value'] ?? null;

        if (filled($value) && ! is_numeric($value)) {
            throw new InvalidArgumentException('The value must be a number, e.g. 49.00.');
        }

        $profile = ['email' => $this->email($config), ...(array) ($this->decoded($config['profile_properties'] ?? null, 'The profile properties') ?? [])];

        $this->klaviyo($run, $config, 'POST', '/events/', ['data' => ['type' => 'event', 'attributes' => array_filter([
            'properties' => (object) ($this->decoded($config['properties'] ?? null, 'The event properties') ?? []),
            'time' => filled($config['time'] ?? null) ? Carbon::parse((string) $config['time'])->utc()->toIso8601String() : null,
            'value' => filled($value) ? (float) $value : null,
            'value_currency' => filled($config['currency'] ?? null) ? strtoupper((string) $config['currency']) : null,
            'unique_id' => filled($config['unique_id'] ?? null) ? (string) $config['unique_id'] : null,
            'metric' => ['data' => ['type' => 'metric', 'attributes' => ['name' => $this->requiredString($config, 'metric_name', 'The event name')]]],
            'profile' => ['data' => ['type' => 'profile', 'attributes' => $profile]],
        ], fn (mixed $item): bool => $item !== null)]]);

        return ['tracked' => true, 'metric' => (string) $config['metric_name'], 'email' => $profile['email']];
    }
}
