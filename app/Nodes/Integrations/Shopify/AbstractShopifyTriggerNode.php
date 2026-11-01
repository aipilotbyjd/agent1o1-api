<?php

namespace App\Nodes\Integrations\Shopify;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Orders, customers, products or checkouts of a store, newest first, as a reader
 * and as a trigger. Shopify filters a list by the time a record was created or
 * last changed (`created_at_min`, `updated_at_min`), so a poll reads the last few
 * hours and the trigger's dedupe decides what is new: by id for something that
 * happens once (a new order), by id and change time for something that can happen
 * again (an order updated). A webhook on the topic (`ShopifySource`) makes the poll
 * look at once, so push and poll share the same filters and cannot disagree.
 */
abstract class AbstractShopifyTriggerNode extends AbstractShopifyNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for records made or changed since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The list endpoint, e.g. `/orders.json`.
     */
    abstract protected function path(): string;

    /**
     * The key the records sit under in Shopify's answer and in the output.
     */
    abstract protected function itemsKey(): string;

    /**
     * The webhook topic that says "something happened" for this trigger, or null
     * when Shopify has none (the trigger then only polls).
     */
    abstract public function webhookTopic(): ?string;

    /**
     * What the "since" and "until" settings and the trigger's dedupe go by.
     *
     * @return 'created_at'|'updated_at'
     */
    protected function stamp(): string
    {
        return 'created_at';
    }

    /**
     * Whether a later change to a record is another event (an order updated) or
     * not (a new order).
     */
    protected function dedupeByChange(): bool
    {
        return $this->stamp() === 'updated_at';
    }

    /**
     * The node's own filters, from its settings.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function filters(array $config): array
    {
        return [];
    }

    /**
     * Filters a preset can't be without.
     *
     * @return array<string, mixed>
     */
    protected function fixedFilters(): array
    {
        return [];
    }

    /**
     * The settings every list node starts from.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function windowFields(): array
    {
        $word = $this->stamp() === 'created_at' ? 'Created' : 'Changed';

        return [
            'since' => Field::advanced(Field::datetime("{$word} after", "Only records {$word} at or after this time.")),
            'until' => Field::advanced(Field::datetime("{$word} before", "Only records {$word} at or before this time.")),
            'limit' => Field::integer('Max results', null, 25, 1, 1000),
        ];
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $stamp = $this->stamp();
        $result = $this->listAll($run, $config, $this->path(), $this->itemsKey(), $this->encode([
            ...$this->filters($config),
            ...$this->fixedFilters(),
            "{$stamp}_min" => filled($config['since'] ?? null) ? Carbon::parse((string) $config['since'])->utc()->toIso8601String() : null,
            "{$stamp}_max" => filled($config['until'] ?? null) ? Carbon::parse((string) $config['until'])->utc()->toIso8601String() : null,
        ]), $this->limit($config));

        // Shopify doesn't promise an order, and a poll starts runs oldest first.
        $records = $result['items'];
        usort($records, fn (array $a, array $b): int => strcmp((string) ($b[$stamp] ?? ''), (string) ($a[$stamp] ?? '')));

        return [$this->itemsKey() => $records, 'count' => count($records), 'has_more' => $result['has_more']];
    }

    protected function triggerItemsPath(): ?string
    {
        return $this->itemsKey();
    }

    /**
     * A record's id, with the time it changed when a change is the event: the same
     * order updated twice is two events, the same order created once is one.
     */
    public function triggerItemId(array $item): string
    {
        return $this->dedupeByChange() ? ($item['id'] ?? '').':'.($item['updated_at'] ?? '') : (string) ($item['id'] ?? '');
    }

    /**
     * The last few hours, with a wide page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'until' => null, 'limit' => 1000];
    }

    /**
     * A safety net: a trigger with a topic is also pushed.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
