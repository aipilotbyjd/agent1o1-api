<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleTasksListCompletedTasksNode extends AbstractGoogleTasksNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for tasks finished since it last ran.
     */
    private const int TRIGGER_WINDOW_HOURS = 24;

    public function type(): string
    {
        return 'google_tasks_list_completed_tasks';
    }

    public function name(): string
    {
        return 'Google Tasks: List Completed Tasks';
    }

    public function description(): string
    {
        return 'Lists the tasks marked done lately.';
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
                'completed_within_hours' => Field::integer('Completed within (hours)', 'Only tasks finished this recently.', 24, 1, 720),
                'max_results' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, $this->tasksPath($config), $config, [
            'maxResults' => $config['max_results'] ?? 50,
            'showCompleted' => 'true',
            // Finished tasks are "hidden" once the list is cleared; they still count.
            'showHidden' => 'true',
            'completedMin' => now()->subHours((int) ($config['completed_within_hours'] ?? 24))->utc()->format('Y-m-d\TH:i:s.000\Z'),
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
     * A task is completed once; reopening and finishing it again is a new
     * completion, told apart by the time it finished.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':'.($item['completed'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Tasks: Task completed';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a task is marked done.';
    }

    /**
     * Look back as far as the poll window, with a wide page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'completed_within_hours' => self::TRIGGER_WINDOW_HOURS, 'max_results' => 100];
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 10;
    }
}
