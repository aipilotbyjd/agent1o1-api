<?php

namespace App\Ai\Tools;

use App\Actions\Agents\CreateAgentSessionAction;
use App\Enums\Agents\AutonomyMode;
use App\Enums\Agents\SubagentTaskStatus;
use App\Jobs\Agents\RunSubagentTaskJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\SubagentTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Starts a subagent: a clone of this agent ("Me") or one of the agents
 * attached as its subagents, on a task in the background and returns
 * straight away, so several can run in parallel. Each runs in a new
 * conversation owned by the subagent, linked back through
 * `parent_session_id`; results are collected with `WaitForSubagentsTool`.
 *
 * `ToolRegistry` only offers this tool in a top-level conversation, so a
 * subagent never starts subagents of its own: see
 * `ToolRegistry::subagentTools()`.
 */
class InvokeAgentTool implements Tool
{
    public const NAME = 'invoke_agent';

    public const SELF = 'Me';

    public const MAX_CONCURRENT = 10;

    /**
     * `$parentMode`/`$parentTestMode` are what this conversation runs under.
     * A subagent's conversation runs under the stricter of that and its own
     * agent's mode, and inherits Test run, so delegating can never get
     * work done with less oversight than the parent has.
     *
     * @param  array<string, Agent>  $targets  Keyed by the name the model uses.
     */
    public function __construct(
        private readonly AgentSession $session,
        private readonly array $targets,
        private readonly ?AutonomyMode $parentMode = null,
        private readonly bool $parentTestMode = false,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        $lines = collect($this->targets)->map(fn (Agent $agent, string $name): string => $name === self::SELF
            ? '- '.self::SELF.': a copy of you with your tools and instructions, for splitting work into parallel subtasks.'
            : "- {$name}: ".($agent->description ?: 'No description.'));

        return 'Starts a subagent on a task in the background and returns immediately, so several subagents can work in parallel. '
            .'Split the work so each subagent gets a DIFFERENT part (for example one item each), never the same task twice. '
            .'Start every subtask first (one call each), then call `'.WaitForSubagentsTool::NAME.'` once to collect all the results. '
            .'A subagent starts fresh and cannot see this conversation, so give it a complete, self-contained task. '
            .'A subagent cannot talk to the user either: get any answer you need from the user yourself first, and never delegate a task that involves asking them something. '
            .'Only delegate work that splits into independent parts, or that the user asked to run in parallel; do a single task yourself. '
            .'Never delegate combining, summarising or reviewing other subagents\' results: a subagent cannot see them. Collect every result, then write the combined answer yourself. '
            .'Available subagents:'."\n".$lines->implode("\n");
    }

    public function handle(Request $request): Stringable|string
    {
        $name = trim((string) $request['agent']);
        $task = trim((string) $request['task']);
        $target = collect($this->targets)->first(fn (Agent $agent, string $key): bool => strcasecmp($key, $name) === 0);

        if ($target === null) {
            return json_encode(['error' => "No subagent named \"{$name}\". Available: ".implode(', ', array_keys($this->targets)).'.']);
        }

        if ($task === '') {
            return json_encode(['error' => 'Give the subagent a task.']);
        }

        SubagentTask::failStale($this->session->id);

        $running = SubagentTask::query()
            ->where('parent_session_id', $this->session->id)
            ->whereIn('status', SubagentTaskStatus::activeValues())
            ->count();

        if ($running >= self::MAX_CONCURRENT) {
            return json_encode(['error' => 'Subagent limit reached. Wait for running subagents to finish, or batch the work into fewer subtasks.']);
        }

        $conversation = app(CreateAgentSessionAction::class)->execute($target, $this->session->user, mb_substr($task, 0, 80));
        $conversation->forceFill([
            'parent_session_id' => $this->session->id,
            'autonomy_mode' => $this->parentMode === null ? null : AutonomyMode::strictest($this->parentMode, $target->autonomy_mode),
            'test_mode' => $this->parentTestMode ?: null,
        ])->save();

        $subagentTask = SubagentTask::query()->create([
            'workspace_id' => $this->session->workspace_id,
            'parent_session_id' => $this->session->id,
            'agent_id' => $target->id,
            'session_id' => $conversation->id,
            'task' => $task,
        ]);

        RunSubagentTaskJob::dispatch($subagentTask->id);

        return json_encode([
            'task_id' => $subagentTask->id,
            'agent' => $target->name,
            'conversation_id' => $conversation->id,
            'status' => 'started',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'agent' => $schema->string()->enum(array_keys($this->targets))
                ->description('Which subagent to start.')->required(),
            'task' => $schema->string()
                ->description('The complete task, including any context the subagent needs.')->required(),
        ];
    }
}
