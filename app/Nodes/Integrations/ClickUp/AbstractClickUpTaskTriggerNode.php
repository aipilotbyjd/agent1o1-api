<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Tasks of a list that were created or changed lately, as a reader and as a
 * trigger. ClickUp filters a list's tasks by creation and change time, so a poll
 * reads only those. A webhook on the list (`ClickUpSource`) makes the poll look at
 * once; the poll's own filters and dedupe decide what runs.
 */
abstract class AbstractClickUpTaskTriggerNode extends AbstractClickUpNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * Whether this node is about tasks created (true) or changed (false) since the look-back.
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
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                ...$this->locationFields(),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->getTimestampMs();
        $limit = $this->limit($config, 50);
        $tasks = [];

        for ($page = 0; count($tasks) < $limit; $page++) {
            $found = array_values(array_filter((array) ($this->clickup($run, $config, 'GET', '/list/'.$this->numericId($config, 'list_id', 'The list').'/task', [
                'page' => $page,
                $this->createdOnly() ? 'date_created_gt' : 'date_updated_gt' => $since,
                'include_closed' => true,
                'order_by' => $this->createdOnly() ? 'created' : 'updated',
                'reverse' => true,
            ])['tasks'] ?? []), 'is_array'));
            array_push($tasks, ...$found);

            if (count($found) < 100) {
                break;
            }
        }

        $tasks = array_slice($tasks, 0, $limit);

        return ['tasks' => array_map($this->shapeTask(...), $tasks), 'count' => count($tasks)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'tasks';
    }

    /**
     * A task that is changed again is a new event; one that is created once is not.
     */
    public function triggerItemId(array $item): string
    {
        return $this->createdOnly() ? (string) ($item['id'] ?? '') : ($item['id'] ?? '').':'.($item['updated_ms'] ?? '');
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
