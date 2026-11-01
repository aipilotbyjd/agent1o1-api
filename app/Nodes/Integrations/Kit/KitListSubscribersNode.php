<?php

namespace App\Nodes\Integrations\Kit;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class KitListSubscribersNode extends AbstractKitNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'kit_list_subscribers';
    }

    public function name(): string
    {
        return 'Kit: List Subscribers';
    }

    public function description(): string
    {
        return 'Lists subscribers, newest first, optionally by state or created or changed since a time. As a trigger it starts the flow for each new subscriber.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'status' => Field::select('State', ['all' => 'Any', 'active' => 'Active', 'inactive' => 'Waiting to confirm', 'bounced' => 'Bounced', 'complained' => 'Complained', 'cancelled' => 'Unsubscribed'], null, 'all'),
                'created_after' => Field::advanced(Field::datetime('Created after')),
                'updated_after' => Field::advanced(Field::datetime('Changed after')),
                'email' => Field::advanced(Field::text('Email is', 'Only the subscriber with this email.')),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'all');
        $subscribers = $this->listAll($run, $config, '/subscribers', 'subscribers', [
            'status' => $status !== 'all' && $status !== '' ? $status : null,
            'created_after' => filled($config['created_after'] ?? null) ? Carbon::parse((string) $config['created_after'])->utc()->toIso8601String() : null,
            'updated_after' => filled($config['updated_after'] ?? null) ? Carbon::parse((string) $config['updated_after'])->utc()->toIso8601String() : null,
            'email_address' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'sort_field' => 'created_at',
            'sort_order' => 'desc',
        ], $this->limit($config, 50, 10000));

        return ['subscribers' => array_map($this->shapeSubscriber(...), $subscribers), 'count' => count($subscribers)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'subscribers';
    }

    public function triggerName(): string
    {
        return 'Kit: New subscriber';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone subscribes in Kit. Choose "Active" to wait until they have confirmed.';
    }

    /**
     * Those created in the look-back window.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'created_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'updated_after' => null, 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
