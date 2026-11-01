<?php

namespace App\Nodes\Integrations\Airtable;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Records of a table that were added or changed lately, as a reader and as a
 * trigger. Airtable can filter by a formula, and `CREATED_TIME()` and
 * `LAST_MODIFIED_TIME()` are in it, so a poll reads only the recent ones. A
 * webhook on the base (`AirtableSource`) makes the poll look at once; the poll's
 * own filters and dedupe decide what runs.
 *
 * Airtable does not say when a record last changed, so a changed record's id for
 * the trigger includes a hash of its fields: it fires again when the content does.
 */
abstract class AbstractAirtableRecordTriggerNode extends AbstractAirtableNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * Whether this node is about records added (true) or changed (false) since the look-back.
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
            'required' => ['base_id', 'table'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->format('Y-m-d\TH:i:s.v\Z');
        $function = $this->createdOnly() ? 'CREATED_TIME()' : 'LAST_MODIFIED_TIME()';

        $records = $this->listRecords($run, $config, ['filterByFormula' => "IS_AFTER({$function}, '{$since}')"], 1000);

        // Airtable can't sort by creation time, so newest first is worked out here.
        usort($records, fn (array $a, array $b): int => strcmp((string) ($b['createdTime'] ?? ''), (string) ($a['createdTime'] ?? '')));

        $records = array_slice($records, 0, $this->limit($config, 50));

        return ['records' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'records';
    }

    public function triggerItemId(array $item): string
    {
        return $this->createdOnly() ? (string) ($item['id'] ?? '') : ($item['id'] ?? '').':'.sha1((string) json_encode($item['fields'] ?? []));
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
