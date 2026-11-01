<?php

namespace App\Nodes\Integrations\GoogleTasks;

use App\Contracts\SchedulesTriggerPolls;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Lists the open tasks that are due soon. As a trigger it fires once for each
 * task as its due day comes within the lead time.
 *
 * Google Tasks keeps only the day a task is due, not a time, so the lead time
 * is counted from the start of the due day (UTC): with 24 hours, a task due
 * on the 5th is reported from the start of the 4th until the end of the 5th.
 * A task that is overdue by more than that is not "due soon" and is left out.
 */
class GoogleTasksListDueSoonTasksNode extends AbstractGoogleTasksNode implements SchedulesTriggerPolls, SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far past the lead time a read looks, so the poll can wake for a task
     * when its time comes rather than only on its interval.
     */
    private const int LOOKAHEAD_DAYS = 7;

    public function type(): string
    {
        return 'google_tasks_list_due_soon_tasks';
    }

    public function name(): string
    {
        return 'Google Tasks: List Tasks Due Soon';
    }

    public function description(): string
    {
        return 'Lists the open tasks due within the next hours. Google Tasks keeps only the due day, so the time counts from the start of that day.';
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
                'hours_before' => Field::integer('Due within (hours)', 'A task is due soon from this many hours before its due day starts until that day ends.', 24, 1, 720),
                'max_results' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    /**
     * `items` are the open tasks inside the lead time now; `later` the open
     * ones due after it, which the trigger uses to know when to look next.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $now = CarbonImmutable::now('UTC');
        $lead = $this->leadHours($config);

        $response = $this->get($run, $this->tasksPath($config), $config, [
            'maxResults' => $config['max_results'] ?? 50,
            'showCompleted' => 'false',
            'dueMin' => $this->dueDate($now->subDay()->toDateString()),
            'dueMax' => $this->dueDate($now->addHours($lead)->addDays(self::LOOKAHEAD_DAYS)->toDateString()),
        ]);

        $items = [];
        $later = [];

        foreach ((array) ($response['items'] ?? []) as $task) {
            $due = is_array($task) && ($task['status'] ?? 'needsAction') === 'needsAction' ? $this->dueAt($task) : null;

            if ($due === null) {
                continue;
            }

            if ($now->gte($due->subHours($lead)) && $now->lt($due->addDay())) {
                $items[] = $task;
            } elseif ($now->lt($due->subHours($lead))) {
                $later[] = $task;
            }
        }

        return ['items' => $items, 'later' => $later];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * A task is due soon once per due date: its id and the day it is due.
     * Moving the due date makes it a new item.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':due:'.substr((string) ($item['due'] ?? ''), 0, 10) : '';
    }

    /**
     * The task, with how long until its due day starts (0 once it has).
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        $due = $this->dueAt($item);

        return [...$item, 'hours_until_due' => $due === null ? null : max(0, (int) round(CarbonImmutable::now('UTC')->diffInSeconds($due, false) / 3600))];
    }

    /**
     * The moment the next task not yet in its lead time gets there.
     */
    public function nextTriggerPollAt(array $output, array $config): ?CarbonInterface
    {
        $now = CarbonImmutable::now('UTC');
        $lead = $this->leadHours($config);
        $next = null;

        foreach ((array) ($output['later'] ?? []) as $task) {
            $due = is_array($task) ? $this->dueAt($task) : null;
            $wakeAt = $due?->subHours($lead);

            if ($wakeAt !== null && $wakeAt->gt($now) && ($next === null || $wakeAt->lt($next))) {
                $next = $wakeAt;
            }
        }

        return $next;
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Tasks: Task due soon';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow a set number of hours before a task\'s due day begins.';
    }

    /**
     * Read as many tasks as the API gives in a page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 100];
    }

    /**
     * How often to look for tasks added since: Tasks has no push, and a task
     * can be added any time. The poll also wakes itself when a task's time comes.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function leadHours(array $config): int
    {
        return max(1, min(720, (int) ($config['hours_before'] ?? 24)));
    }

    /**
     * The start of a task's due day, in UTC, or null when it has none.
     *
     * @param  array<string, mixed>  $task
     */
    private function dueAt(array $task): ?CarbonImmutable
    {
        $due = $task['due'] ?? null;

        return is_string($due) && preg_match('/^\d{4}-\d{2}-\d{2}/', $due) === 1
            ? CarbonImmutable::parse(substr($due, 0, 10), 'UTC')->startOfDay()
            : null;
    }
}
