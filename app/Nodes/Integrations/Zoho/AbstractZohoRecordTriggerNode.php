<?php

namespace App\Nodes\Integrations\Zoho;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Records of one module that were created or changed lately, as a reader and as a
 * trigger. COQL filters and orders by `Created_Time` and `Modified_Time`, so a poll
 * reads only the recent ones, newest first. A notification channel on the module
 * (`ZohoSource`) makes the poll look at once; the poll's own filters and dedupe
 * decide what runs.
 */
abstract class AbstractZohoRecordTriggerNode extends AbstractZohoNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The module this node is about, e.g. `Leads`.
     */
    abstract protected function module(): string;

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
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->format('Y-m-d\TH:i:s').'+00:00';
        $stamp = $this->createdOnly() ? 'Created_Time' : 'Modified_Time';
        $module = $this->module();

        $records = $this->coql($run, $config, 'select '.implode(', ', $this->selectFields($config, self::MODULES[$module]))." from {$module} where {$stamp} >= '{$since}' order by {$stamp} desc", $this->limit($config, 50));

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
