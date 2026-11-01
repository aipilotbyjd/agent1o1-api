<?php

namespace App\Nodes\Integrations\Kit;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Subscribers in the "cancelled" state, newest change first, since a look-back.
 * The change time is part of the item's id, so a person who resubscribes and
 * leaves again is a new event.
 */
class KitListUnsubscribesNode extends AbstractKitNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'kit_list_unsubscribes';
    }

    public function name(): string
    {
        return 'Kit: List Unsubscribes';
    }

    public function description(): string
    {
        return 'Lists the subscribers who unsubscribed lately, newest first. As a trigger it starts the flow for each unsubscribe.';
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
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $subscribers = $this->listAll($run, $config, '/subscribers', 'subscribers', [
            'status' => 'cancelled',
            'updated_after' => Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->toIso8601String(),
            'sort_field' => 'updated_at',
            'sort_order' => 'desc',
        ], $this->limit($config, 25));

        return ['subscribers' => array_map($this->shapeSubscriber(...), $subscribers), 'count' => count($subscribers)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'subscribers';
    }

    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['updated_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Kit: Unsubscribed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone unsubscribes in Kit.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
