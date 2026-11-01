<?php

namespace App\Services\Triggers;

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Contracts\Triggers\RunStarter;
use App\Enums\Agents\AutonomyMode;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\TemplateResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Starts a trigger's workflow or agent.
 *
 * A workflow is started through StartWorkflowRunAction, exactly as the API
 * does, at the trigger's own node. An agent gets a fresh session and one
 * message. Both end in a `runs` row, which the event then points at.
 *
 * Every method reaches the target through targetFor(), so a deleted, missing
 * or other-workspace target is "not runnable" in one place.
 */
class TargetRunStarter implements RunStarter
{
    public function __construct(
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly CreateAgentSessionAction $createAgentSession,
        private readonly SendAgentMessageAction $sendAgentMessage,
        private readonly TemplateResolver $templates,
    ) {}

    /**
     * Whether the target exists in the trigger's workspace and, for a
     * workflow, is published.
     */
    public function canRun(Trigger $trigger): bool
    {
        $target = $this->targetFor($trigger);

        return match (true) {
            // An unpublished workflow has no version to run. Refusing here lets
            // intake record a skipped event instead of a failed start.
            $target instanceof Workflow => $target->isPublished(),
            $target instanceof Agent => true,
            default => false,
        };
    }

    /**
     * Whether the target has a run that hasn't finished.
     */
    public function isAlreadyRunning(Trigger $trigger): bool
    {
        return $this->runsFor($trigger)?->whereIn('status', RunStatus::inFlight())->exists() ?? false;
    }

    /**
     * Pending and running runs of the target.
     */
    public function activeRuns(Trigger $trigger): int
    {
        return $this->runsFor($trigger)?->whereIn('status', [RunStatus::Pending, RunStatus::Running])->count() ?? 0;
    }

    /**
     * Every run of the trigger's target, or null when it has none to run.
     *
     * @return Builder<Run>|null
     */
    private function runsFor(Trigger $trigger): ?Builder
    {
        $target = $this->targetFor($trigger);

        return match (true) {
            $target instanceof Workflow => Run::query()->where('workflow_id', $target->id),
            $target instanceof Agent => Run::query()
                ->where('runnable_type', (new AgentSession)->getMorphClass())
                ->whereIn('runnable_id', $target->sessions()->select('id')),
            default => null,
        };
    }

    /**
     * Start the target and return the run's id.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException when the target disappeared after intake
     */
    public function start(Trigger $trigger, array $payload): string
    {
        $target = $this->targetFor($trigger);

        return match (true) {
            $target instanceof Workflow => $this->startWorkflow($trigger, $target, $payload),
            $target instanceof Agent => $this->startAgent($trigger, $target, $payload),
            // Only if the target was deleted after intake. Throwing hands it to
            // FireTriggerEvent's retries and breaker, like any other failure.
            default => throw new RuntimeException("Trigger {$trigger->id} has no runnable target."),
        };
    }

    /**
     * Run the workflow's published version, starting at the trigger's node.
     *
     * @param  array<string, mixed>  $payload
     */
    private function startWorkflow(Trigger $trigger, Workflow $workflow, array $payload): string
    {
        return $this->startWorkflowRun->execute(
            $workflow,
            $payload,
            // The trigger's creator stands in for the person who ran it: run
            // history and per-user skills attribute the run to them. Someone
            // who has left the workspace is replaced by its owner.
            $trigger->actingUser(),
            $trigger->type->value,
            entryNodeKey: $trigger->trigger_node_key,
        )->id;
    }

    /**
     * Start the agent in a fresh session with one message.
     *
     * A new session per event, so one event's conversation never leaks into
     * the next one's context.
     *
     * @param  array<string, mixed>  $payload
     */
    private function startAgent(Trigger $trigger, Agent $agent, array $payload): string
    {
        $session = $this->createAgentSession->execute(
            $agent,
            $trigger->actingUser(),
            "{$trigger->type->value} trigger #{$trigger->id}",
        );

        // A trigger can run the agent in a different mode than chat, usually
        // stricter, since nobody is watching a triggered turn.
        $session->forceFill([
            'autonomy_mode' => AutonomyMode::tryFrom((string) ($trigger->config['autonomy_mode'] ?? '')),
            'test_mode' => isset($trigger->config['test_mode']) ? (bool) $trigger->config['test_mode'] : null,
        ])->save();

        $this->sendAgentMessage->execute($session, $this->messageFor($trigger, $payload), $trigger->type->value);

        // One run per turn, and this new session has exactly one turn.
        return $session->runs()->latest('id')->value('id')
            ?? throw new RuntimeException("Agent session {$session->id} produced no run.");
    }

    /**
     * The message the agent is sent.
     *
     * The trigger's `config.message` templated against the payload
     * ("Triage: {{ payload.issue.title }}"), or without one the raw payload
     * as JSON, for the agent's own instructions to interpret.
     *
     * @param  array<string, mixed>  $payload
     */
    private function messageFor(Trigger $trigger, array $payload): string
    {
        $message = $this->templates->resolve(
            $trigger->config['message'] ?? null,
            ['payload' => $payload, 'trigger' => ['id' => $trigger->id, 'type' => $trigger->type->value]],
        );

        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        return $payload === []
            ? "The {$trigger->type->value} trigger fired with no payload."
            : json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * The trigger's target, or null when it can't run at all: deleted, never
     * existed, or in a different workspace from the trigger (the tenancy
     * check).
     */
    private function targetFor(Trigger $trigger): ?Model
    {
        $target = $trigger->target;

        if ($target === null || $target->workspace_id !== $trigger->workspace_id) {
            return null;
        }

        return $target;
    }
}
