<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Deals, people, organizations or activities that were added or changed lately, as
 * a reader and as a trigger. Pipedrive lists a collection newest first by add time
 * or update time, so a poll reads the newest pages and keeps those within the
 * look-back. A webhook on the object (`PipedriveSource`) makes the poll look at
 * once; the poll's own filters and dedupe decide what runs.
 */
abstract class AbstractPipedriveRecordTriggerNode extends AbstractPipedriveNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The collection this node is about, e.g. `deals`.
     */
    abstract protected function collection(): string;

    /**
     * Whether it is about records added (true) or changed (false) since the look-back.
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
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc();
        $stamp = $this->createdOnly() ? 'add_time' : 'update_time';
        $collection = $this->collection();

        $records = $this->listAll($run, $config, '/'.$collection, array_filter([
            'sort' => $stamp.' DESC',
            'status' => $collection === 'deals' ? 'all_not_deleted' : null,
            'user_id' => $collection === 'activities' ? 0 : null,
        ], fn (mixed $value): bool => $value !== null), 500);

        // Pipedrive writes times as "2026-03-01 10:00:00" in UTC.
        $records = array_values(array_filter($records, fn (array $record): bool => isset($record[$stamp]) && Carbon::parse($record[$stamp], 'UTC')->greaterThanOrEqualTo($since)));
        $records = array_slice($records, 0, $this->limit($config, 50));

        return [$collection => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }

    protected function triggerItemsPath(): ?string
    {
        return $this->collection();
    }

    /**
     * A record that is changed again is a new event; one that is added once is not.
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
