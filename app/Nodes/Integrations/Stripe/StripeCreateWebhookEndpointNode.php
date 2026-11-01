<?php

namespace App\Nodes\Integrations\Stripe;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class StripeCreateWebhookEndpointNode extends AbstractStripeNode
{
    public function type(): string
    {
        return 'stripe_create_webhook_endpoint';
    }

    public function name(): string
    {
        return 'Stripe: Create Webhook Endpoint';
    }

    public function description(): string
    {
        return 'Sets up a webhook endpoint. Its signing secret is only shown now, in the result.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url', 'events'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('URL', 'Must be https.', 'https://example.com/stripe'),
                'events' => Field::list('Events', 'Event names such as invoice.paid, or * for all of them.', 'invoice.paid'),
                'description' => Field::advanced(Field::text('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $url = $this->requiredString($config, 'url', 'The URL');

        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('The URL must start with https://.');
        }

        $events = array_values(array_filter(array_map(fn (mixed $event): string => trim((string) $event), (array) ($config['events'] ?? []))));

        if ($events === []) {
            throw new InvalidArgumentException('Name at least one event, or * for all of them.');
        }

        return $this->stripe($run, $config, 'POST', '/webhook_endpoints', [
            'url' => $url,
            'enabled_events' => $events,
            ...$this->filled($config, ['description']),
        ]);
    }
}
