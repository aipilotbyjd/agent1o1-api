<?php

namespace App\Nodes\Integrations\Kit;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class KitListTaggedSubscribersNode extends AbstractKitNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'kit_list_tagged_subscribers';
    }

    public function name(): string
    {
        return 'Kit: List Tagged Subscribers';
    }

    public function description(): string
    {
        return 'Lists the subscribers with a tag, most recently tagged first. As a trigger it starts the flow whenever someone gets the tag.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['tag_id'],
            'properties' => [
                ...$this->credentialFields(),
                'tag_id' => $this->tagField(),
                'tagged_after' => Field::advanced(Field::datetime('Tagged after', 'Only people tagged at or after this time.')),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tag = $this->numericId($config, 'tag_id', 'The tag');
        $subscribers = $this->listAll($run, $config, "/tags/{$tag}/subscribers", 'subscribers', [
            'tagged_after' => filled($config['tagged_after'] ?? null) ? Carbon::parse((string) $config['tagged_after'])->utc()->toIso8601String() : null,
            'status' => 'all',
            'sort_order' => 'desc',
        ], $this->limit($config, 50, 10000));

        return ['subscribers' => array_map(fn (array $subscriber): array => [...$this->shapeSubscriber($subscriber), 'tagged_at' => $subscriber['tagged_at'] ?? null, 'tag_id' => $tag], $subscribers), 'count' => count($subscribers)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'subscribers';
    }

    /**
     * The person and the moment of tagging: a tag can be removed and put on again.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['tagged_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Kit: Tag added';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a subscriber gets a Kit tag.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'tagged_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
