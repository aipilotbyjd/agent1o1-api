<?php

namespace App\Nodes\Integrations\Monday;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Items of a board that were added or changed lately, as a reader and as a
 * trigger. monday.com can order a board's items by their creation or last-update
 * time, so a poll reads the newest page and stops where they get old. A webhook on
 * the board (`MondaySource`) makes the poll look at once; the poll's own filters
 * and dedupe decide what runs.
 */
abstract class AbstractMondayItemTriggerNode extends AbstractMondayNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * Whether this node is about items added (true) or changed (false) since the look-back.
     */
    abstract protected function createdOnly(): bool;

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'group_id' => Field::advanced($this->groupField('Only group')),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc();
        $stamp = $this->createdOnly() ? 'created_at' : 'updated_at';
        $group = filled($config['group_id'] ?? null) ? $this->shortId($config, 'group_id', 'The group') : null;

        $items = $this->readItems($run, $config, $this->numericId($config, 'board_id', 'The board'), $group, $this->createdOnly() ? '__creation_log__' : '__last_updated__', 500);
        $items = array_values(array_filter($items, fn (array $item): bool => isset($item[$stamp]) && Carbon::parse($item[$stamp])->greaterThanOrEqualTo($since)));
        $items = array_slice($items, 0, $this->limit($config, 50));

        return ['items' => array_map($this->shapeItem(...), $items), 'count' => count($items)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * An item that is changed again is a new event; one that is created once is not.
     */
    public function triggerItemId(array $item): string
    {
        return $this->createdOnly() ? (string) ($item['id'] ?? '') : ($item['id'] ?? '').':'.($item['updated_at'] ?? '');
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
