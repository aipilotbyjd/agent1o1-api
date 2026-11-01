<?php

namespace App\Ai\Tools;

use App\Enums\Agents\SubagentTaskStatus;
use App\Models\Agents\AgentSession;
use App\Models\Agents\SubagentTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Collects the results of the subagents this conversation started with
 * `InvokeAgentTool`. Waits until they have all finished, up to
 * `$waitSeconds`; anything still running is reported as such so the model
 * can wait again. A result is only reported once (`collected_at`).
 *
 * A chat turn usually runs inside the HTTP request that sent the message, so
 * the wait stays well inside `AllowLongAgentTurn::SECONDS`. It is long
 * enough for a typical batch to finish in one call: a result reported while
 * others are still running tempts the model to answer, or to hand the
 * combining to a subagent that can't see the results. Whatever is still
 * running is reported with a note to call again.
 */
class WaitForSubagentsTool implements Tool
{
    public const NAME = 'wait_for_subagents';

    public function __construct(
        private readonly AgentSession $session,
        private readonly int $waitSeconds = 60,
        private readonly int $pollMilliseconds = 1000,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Waits for the subagents you started to finish and returns their answers. '
            .'Call it once after starting all your subtasks. If some are still running when it returns, call it again, '
            .'and keep calling until all are collected before you answer or combine anything.';
    }

    public function handle(Request $request): Stringable|string
    {
        SubagentTask::failStale($this->session->id);

        $deadline = microtime(true) + $this->waitSeconds;

        do {
            $tasks = $this->uncollected();

            if ($tasks->isEmpty()) {
                return json_encode(['results' => [], 'note' => 'No subagent results are waiting to be collected.']);
            }

            if ($tasks->every(fn (SubagentTask $task): bool => $task->status->isFinished())) {
                break;
            }

            usleep($this->pollMilliseconds * 1000);
        } while (microtime(true) < $deadline);

        $finished = $tasks->filter(fn (SubagentTask $task): bool => $task->status->isFinished());
        SubagentTask::query()->whereKey($finished->modelKeys())->update(['collected_at' => now()]);

        $stillRunning = $tasks->reject(fn (SubagentTask $task): bool => $task->status->isFinished());

        return json_encode(array_filter([
            'results' => $finished->map(fn (SubagentTask $task): array => array_filter([
                'agent' => $task->agent?->name,
                'task' => $task->task,
                'status' => $task->status->value,
                'answer' => $task->result,
                'error' => $task->error,
            ], fn ($value) => $value !== null))->values()->all(),
            'still_running' => $stillRunning
                ->map(fn (SubagentTask $task): string => "{$task->agent?->name}: {$task->task}"
                    .($task->status === SubagentTaskStatus::AwaitingApproval ? ' (waiting for a person to approve one of its actions)' : ''))
                ->values()
                ->all(),
            'note' => $stillRunning->isNotEmpty()
                ? "{$stillRunning->count()} subagent(s) are still working. Call ".self::NAME.' again to collect them before you answer or combine anything.'
                : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return Collection<int, SubagentTask>
     */
    private function uncollected(): Collection
    {
        return SubagentTask::query()
            ->with('agent:id,name')
            ->where('parent_session_id', $this->session->id)
            ->whereNull('collected_at')
            ->oldest()
            ->get();
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
