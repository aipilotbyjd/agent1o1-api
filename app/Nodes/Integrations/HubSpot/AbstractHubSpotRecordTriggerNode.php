<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Contacts, companies, deals or tickets that were created or changed lately, as a
 * reader and as a trigger. HubSpot searches by creation and modification time, so
 * a poll reads only the recent ones, newest first.
 *
 * HubSpot has no webhook a private app token can register (webhooks belong to a
 * developer app), so this polls.
 */
abstract class AbstractHubSpotRecordTriggerNode extends AbstractHubSpotNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The object type this node is about, e.g. `contacts`.
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
                'read_properties' => Field::advanced(Field::list('More properties to read', 'One property name per row.')),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->getTimestampMs();
        $property = $this->createdOnly() ? 'createdate' : self::OBJECTS[$this->object()]['modified'];

        $records = $this->search($run, $config, $this->object(), [['propertyName' => $property, 'operator' => 'GTE', 'value' => (string) $since]], $this->readProperties($config, $this->object()), $this->limit($config, 50), $property);

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
        if ($this->createdOnly()) {
            return (string) ($item['id'] ?? '');
        }

        $properties = (array) ($item['properties'] ?? []);

        return ($item['id'] ?? '').':'.($properties[self::OBJECTS[$this->object()]['modified']] ?? $item['updated_at'] ?? '');
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
