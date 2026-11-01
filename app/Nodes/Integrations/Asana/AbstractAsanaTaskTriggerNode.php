<?php

namespace App\Nodes\Integrations\Asana;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Tasks of a project that changed lately, as a reader and as a trigger: new,
 * completed, or changed in any way.
 *
 * Asana lists a project's tasks changed since a time (`modified_since`), so a poll
 * reads only those, then keeps the ones that match the kind. A webhook on the
 * project (`AsanaSource`) makes the poll look at once; the poll's own filters
 * and dedupe decide what runs.
 */
abstract class AbstractAsanaTaskTriggerNode extends AbstractAsanaNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * Which tasks this node keeps: `created`, `completed` or `modified`.
     */
    abstract protected function kind(): string;

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'workspace_id' => $this->workspaceField(),
                'project_id' => $this->projectField('Project to watch'),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc();
        $kind = $this->kind();
        $stamp = $kind === 'created' ? 'created_at' : ($kind === 'completed' ? 'completed_at' : 'modified_at');

        $tasks = $this->listAll($run, $config, '/projects/'.$this->gid($config, 'project_id', 'The project').'/tasks', [
            'opt_fields' => self::TASK_FIELDS,
            'modified_since' => $since->toIso8601String(),
        ], 1000);

        $tasks = array_values(array_filter($tasks, fn (array $task): bool => isset($task[$stamp])
            && Carbon::parse($task[$stamp])->greaterThanOrEqualTo($since)
            && ($kind !== 'completed' || ($task['completed'] ?? false) === true)));

        usort($tasks, fn (array $a, array $b): int => strcmp((string) $b[$stamp], (string) $a[$stamp]));

        $tasks = array_slice($tasks, 0, $this->limit($config, 50));

        return ['tasks' => array_map($this->shapeTask(...), $tasks), 'count' => count($tasks)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'tasks';
    }

    /**
     * A task that is changed again is a new event; one that is created or completed once is not.
     */
    public function triggerItemId(array $item): string
    {
        return $this->kind() === 'modified' ? ($item['id'] ?? '').':'.($item['modified_at'] ?? '') : (string) ($item['id'] ?? '');
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
