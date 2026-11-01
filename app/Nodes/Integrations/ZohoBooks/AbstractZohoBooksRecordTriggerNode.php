<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Invoices, payments or customers that were added lately, as a reader and as a
 * trigger. Books lists a collection newest first by creation time, so a poll reads
 * the newest pages and keeps those within the look-back; the poll's own dedupe by
 * id decides what runs. Zoho Books can't register webhooks through its API (they
 * are set up in its own settings), so this trigger polls; the generic Webhook
 * trigger takes a Books webhook for instant delivery.
 */
abstract class AbstractZohoBooksRecordTriggerNode extends AbstractZohoBooksNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The path of the collection, e.g. `/invoices`.
     */
    abstract protected function path(): string;

    /**
     * The key the collection is under in the answer and in this node's output, e.g. `invoices`.
     */
    abstract protected function collection(): string;

    /**
     * Any other filter the list needs, e.g. only customers.
     *
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [];
    }

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
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only records this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc();

        $records = $this->listAll($run, $config, $this->path(), $this->collection(), [...$this->filters(), 'sort_column' => 'created_time', 'sort_order' => 'D'], 500);

        $records = array_values(array_filter($records, fn (array $record): bool => filled($record['created_time'] ?? null) && Carbon::parse((string) $record['created_time'])->greaterThanOrEqualTo($since)));
        $records = array_slice($records, 0, $this->limit($config, 50));

        return [$this->collection() => array_map($this->shape(...), $records), 'count' => count($records)];
    }

    protected function triggerItemsPath(): ?string
    {
        return $this->collection();
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
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
