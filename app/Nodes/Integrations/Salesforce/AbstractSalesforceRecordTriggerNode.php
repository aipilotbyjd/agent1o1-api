<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Records of one object that were created or changed lately, as a reader and as a
 * trigger. SOQL filters and orders by `CreatedDate` and `SystemModstamp`, so a poll
 * reads only the recent ones, newest first.
 *
 * Salesforce pushes changes through its streaming API (Change Data Capture), a
 * long-lived connection this app doesn't keep, so this polls.
 */
abstract class AbstractSalesforceRecordTriggerNode extends AbstractSalesforceNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The object this node is about, e.g. `Lead`.
     */
    abstract protected function object(): string;

    /**
     * Whether it is about records created (true) or changed (false) since the look-back.
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
                'read_fields' => Field::advanced(Field::list('More fields to read', 'API names, one per row.')),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 2000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->format('Y-m-d\TH:i:s\Z');
        $stamp = $this->createdOnly() ? 'CreatedDate' : 'SystemModstamp';
        $object = $this->object();
        $limit = $this->limit($config, 50);

        $records = $this->query($run, $config, 'SELECT '.implode(', ', $this->selectFields($config, self::OBJECTS[$object]))." FROM {$object} WHERE {$stamp} >= {$since} ORDER BY {$stamp} DESC LIMIT {$limit}", $limit);

        return ['records' => array_map($this->shapeRecord(...), $records), 'count' => count($records)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'records';
    }

    /**
     * A record that is changed again is a new event; one that is created once is not.
     */
    public function triggerItemId(array $item): string
    {
        return $this->createdOnly() ? (string) ($item['id'] ?? '') : ($item['id'] ?? '').':'.($item['updated_at'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 2000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
