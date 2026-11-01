<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleTasksListTasksNode extends AbstractGoogleTasksNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_tasks_list_tasks';
    }

    public function name(): string
    {
        return 'Google Tasks: List Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks in a task list.';
    }

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
                'task_list_id' => Field::dynamic('Task list', 'google_tasks.lists', 'Defaults to your main list.', '@default'),
                'show_completed' => Field::boolean('Include completed tasks'),
                'due_before' => Field::datetime('Due before', 'Only tasks due before this date.'),
                'due_after' => Field::datetime('Due after', 'Only tasks due on or after this date.'),
                'max_results' => Field::integer('Max results', null, 20, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $includeCompleted = (bool) ($config['show_completed'] ?? false);

        return $this->get($run, $this->tasksPath($config), $config, [
            'maxResults' => $config['max_results'] ?? 20,
            'showCompleted' => $includeCompleted ? 'true' : 'false',
            'showHidden' => $includeCompleted ? 'true' : null,
            'dueMax' => $this->dueDate($config['due_before'] ?? null),
            'dueMin' => $this->dueDate($config['due_after'] ?? null),
            'updatedMin' => $config['updated_min'] ?? null,
        ]);
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Tasks: New task';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new task is added to a task list.';
    }

    /**
     * Read the tasks changed in the last day, not the whole list: a task made
     * since the last poll was changed since the last poll, and one already
     * seen is never run twice.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 100, 'updated_min' => now()->subDay()->utc()->format('Y-m-d\TH:i:s.000\Z')];
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
