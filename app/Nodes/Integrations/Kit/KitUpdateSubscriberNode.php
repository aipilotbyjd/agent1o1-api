<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class KitUpdateSubscriberNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_update_subscriber';
    }

    public function name(): string
    {
        return 'Kit: Update Subscriber';
    }

    public function description(): string
    {
        return 'Changes a subscriber\'s email, first name or custom fields. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subscriber'],
            'properties' => [
                ...$this->credentialFields(),
                'subscriber' => Field::text('Subscriber', 'Their email address or Kit id.', 'ada@example.com'),
                'new_email' => Field::text('New email'),
                'first_name' => Field::text('First name'),
                'fields' => Field::advanced(Field::json('Custom fields (JSON)', 'Fields by name, e.g. {"company": "Acme"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->subscriberId($run, $config);
        $body = [
            'email_address' => filled($config['new_email'] ?? null) ? $this->email($config, 'new_email') : null,
            'first_name' => filled($config['first_name'] ?? null) ? (string) $config['first_name'] : null,
            'fields' => $this->decoded($config['fields'] ?? null, 'The custom fields'),
        ];

        if (array_filter($body, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeSubscriber((array) data_get((array) $this->kit($run, $config, 'PUT', "/subscribers/{$id}", $body), 'subscriber', []));
    }
}
