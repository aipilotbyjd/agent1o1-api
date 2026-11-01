<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Tasks or bugs that were added or changed lately across the portal, as a reader
 * and as a trigger. A poll reads the newest records (asking for newest first, then
 * sorting what came back itself, since a portal can be large) and keeps those within
 * the look-back; the poll's own dedupe decides what runs. Zoho Projects registers
 * webhooks in its own settings, not through its API, so this trigger polls; the
 * generic Webhook trigger takes a Projects webhook for instant delivery.
 */
abstract class AbstractZohoProjectsRecordTriggerNode extends AbstractZohoProjectsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The path of the portal-wide collection, e.g. `/tasks`.
     */
    abstract protected function path(): string;

    /**
     * The key the collection is under in the answer and in this node's output, e.g. `tasks`.
     */
    abstract protected function collection(): string;

    /**
     * The key the records are under in Projects' answer, when it differs from the output key.
     */
    protected function answerKey(): string
    {
        return $this->collection();
    }

    /**
     * Whether it is about records added (true) or changed (false) since the look-back.
     */
    abstract protected function createdOnly(): bool;

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    abstract protected function shape(array $record): array;

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
                'project_id' => Field::advanced($this->projectField()),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only records this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc();
        $stamp = $this->createdOnly() ? 'created_time' : 'last_updated_time';
        $project = $this->optionalId($config, 'project_id', 'The project');

        $records = $this->listAll($run, $config, $project === null ? $this->path() : "/projects/{$project}".$this->path(), $this->answerKey(), ['sort_by' => 'DESC('.$stamp.')'], 500);

        $records = array_values(array_filter($records, fn (array $record): bool => ($this->when($record[$stamp] ?? null)?->greaterThanOrEqualTo($since)) === true));
        usort($records, fn (array $a, array $b): int => $this->when($b[$stamp])->getTimestamp() <=> $this->when($a[$stamp])->getTimestamp());
        $records = array_slice($records, 0, $this->limit($config, 50));

        return [$this->collection() => array_map($this->shape(...), $records), 'count' => count($records)];
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
