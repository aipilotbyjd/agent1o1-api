<?php

namespace App\Services\Agents;

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Artifacts\StoreArtifactAction;
use App\Ai\Agents\EmbeddedAgent;
use App\Ai\Agents\WorkspaceAgent;
use App\Ai\ResponseUsage;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\RunStatus;
use App\Events\Agents\AgentActionsChanged;
use App\Events\Agents\AgentActionsRequested;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Exceptions\AgentTurnPausedException;
use App\Exceptions\RunStateException;
use App\Models\Agents\Agent as AgentModel;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\Skill;
use App\Models\Artifacts\Artifact;
use App\Models\Runs\Run;
use App\Services\Agents\Approvals\AgentActionExecutor;
use App\Services\Agents\Approvals\AutonomyResolver;
use App\Services\Agents\Approvals\PausedTurn;
use App\Services\Agents\Approvals\Resumption;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Tools\ToolNameResolver;
use Throwable;

/**
 * The Agent layer's engine entry point: mirrors `WorkflowRunner`'s role for
 * the Workflow engine.
 *
 * One `Run` per turn (`runnable_type = AgentSession::class`): a session's
 * `runs()` relation lists its full turn history, the same way a workflow's
 * `Run` list works. See docs/WORKFLOWS_PLAN.md's `runs` table note on why
 * agent invocations and workflow executions share one table. This turn's own
 * `Run` doubles as the execution context `NodeTool::handle()` passes to
 * `NodeContract::execute()` for any tool call made during it.
 *
 * A turn can stop partway on calls that need a person's approval (see
 * `Approvals\ActionGate`): it is then paused, not completed (its `Run`
 * waits in `awaiting_approval`) and `resume()` continues it once every
 * waiting call is decided.
 */
class AgentRunner
{
    /**
     * How long a `running` turn blocks the next message to its session. A
     * turn older than this is treated as abandoned (a killed worker) rather
     * than left to lock the conversation forever.
     */
    public const int TURN_STALE_AFTER_MINUTES = 15;

    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly SkillInjector $skillInjector,
        private readonly CreditGate $creditGate,
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly ByokProviderRegistrar $byok,
        private readonly CreateAgentSessionAction $createSession,
        private readonly StoreArtifactAction $storeArtifact,
        private readonly AutonomyResolver $autonomy,
        private readonly AgentActionExecutor $executor,
        private readonly SubagentTaskTracker $subagentTasks,
    ) {}

    /**
     * `$skill` is one the person picked for this message; its instructions
     * are added to this turn's prompt: see `SkillInjector::chosenSkillSection()`.
     *
     * `$replacing` is an earlier user message this one stands in for (a
     * retry or an edit): it and everything after it are deleted before the
     * new message is stored, so the abandoned turn never reaches the model.
     *
     * @param  array<int, UploadedFile>  $attachments  Files the member attached to this message.
     */
    public function run(AgentSession $session, string $message, string $triggerType = 'manual', array $attachments = [], ?Skill $skill = null, ?string $replacing = null): AgentMessage
    {
        $turn = $this->openTurn($session, $message, $triggerType, $attachments, $skill, $replacing);

        try {
            $response = $turn->agent->prompt($message, $turn->attachments, provider: $turn->provider, model: $turn->model);

            return $this->settleTurn($turn, $response);
        } catch (Throwable $e) {
            $this->failTurn($turn->run, $e);

            throw $e;
        }
    }

    /**
     * Continues a turn that paused for approvals, once every waiting call
     * has a decision (`ResolveAgentActionsAction` checks that). The decided
     * calls are settled first (approved ones run, exactly once) then the
     * model is handed the outcomes and carries on. It may finish, or pause
     * again on a new call that needs approval.
     *
     * The reply is written onto the same assistant message the turn paused
     * on, so one user message still gets one reply, and the turn's `Run` is
     * the same one: it goes back to `running`, then settles as usual.
     */
    public function resume(Run $run): AgentMessage
    {
        $resumption = $this->openResume($run);

        try {
            $response = $resumption->turn->agent->prompt($resumption->decisions, provider: $resumption->turn->provider, model: $resumption->turn->model);

            return $this->settleResumedTurn($resumption, $response);
        } catch (Throwable $e) {
            $this->failTurn($run, $e);

            throw $e;
        }
    }

    /**
     * `resume()`, delivered as a stream: see `streamBegun()` for who owns
     * failure.
     */
    public function resumeStream(Run $run): StreamedTurn
    {
        $resumption = $this->openResume($run);

        $response = $resumption->turn->agent->stream($resumption->decisions, provider: $resumption->turn->provider, model: $resumption->turn->model);

        $response->then(function (StreamedAgentResponse $streamed) use ($resumption): void {
            $this->settleResumedTurn($resumption, $streamed);
        });

        return new StreamedTurn($resumption->turn->run, $response);
    }

    /**
     * Opens a turn that is delivered later, from the queue: everything
     * `openTurn()` does that can be refused or needs the uploaded files
     * (credit gate, the turn's `Run`, the user's message and its attachments)
     * so the sender hears about a busy conversation or an empty balance
     * right away. The turn stays `running` until `RunAgentTurnJob` streams
     * it with `streamBegun()`.
     *
     * @param  array<int, UploadedFile>  $attachments  Files the member attached to this message.
     * @param  string|null  $replacing  An earlier user message this one replaces: see `run()`.
     */
    public function beginTurn(AgentSession $session, string $message, string $triggerType = 'manual', array $attachments = [], ?Skill $skill = null, ?string $replacing = null): Run
    {
        return $this->startTurn($session, $message, $triggerType, $attachments, $skill, $replacing)[0];
    }

    /**
     * Streams a turn `beginTurn()` opened: the provider call and the reply.
     * The caller iterates the returned `StreamableAgentResponse`
     * (`AgentTurnBroadcaster` does, inside `RunAgentTurnJob`) and the turn is
     * closed out by the `then()` callback registered here once the provider
     * finishes.
     *
     * The caller owns failure: nothing runs until the stream is iterated, so
     * an exception surfaces *there*, not here, and whoever iterates must call
     * `failTurn()`, otherwise the turn's `Run` would sit in `running`
     * forever.
     */
    public function streamBegun(Run $run): StreamedTurn
    {
        try {
            $session = $run->runnable;

            if (! $session instanceof AgentSession) {
                throw new InvalidArgumentException("Run [{$run->id}] is not an agent conversation turn.");
            }

            $userMessage = $session->messages()->findOrFail($run->input['user_message_id'] ?? null);
            $skill = $userMessage->skill_id !== null ? Skill::query()->find($userMessage->skill_id) : null;
            $attachments = Artifact::query()->whereKey($run->input['attachment_ids'] ?? [])->get()->all();

            $turn = $this->buildTurn($session, $run, $userMessage, $attachments, $skill);
        } catch (Throwable $e) {
            $this->failTurn($run, $e);

            throw $e;
        }

        $response = $turn->agent->stream($userMessage->content, $turn->attachments, provider: $turn->provider, model: $turn->model);

        $response->then(function (StreamedAgentResponse $streamed) use ($turn): void {
            $this->settleTurn($turn, $streamed);
        });

        return new StreamedTurn($turn->run, $response);
    }

    /**
     * Everything that happens before the provider is called: `startTurn()`'s
     * setup, then the SDK agent built from the version this conversation is
     * pinned to.
     *
     * @param  array<int, UploadedFile>  $attachments
     */
    private function openTurn(AgentSession $session, string $message, string $triggerType, array $attachments, ?Skill $skill = null, ?string $replacing = null): AgentTurn
    {
        [$run, $userMessage, $stored] = $this->startTurn($session, $message, $triggerType, $attachments, $skill, $replacing);

        try {
            return $this->buildTurn($session, $run, $userMessage, $stored, $skill);
        } catch (Throwable $e) {
            $this->failTurn($run, $e);

            throw $e;
        }
    }

    /**
     * The part of a turn that can be refused or needs the uploaded files:
     * credit gate (before the turn's `Run` exists: a workspace out of
     * credits is refused up front rather than after the model call is paid
     * for), the `Run`, the user's message and its attachments.
     *
     * @param  array<int, UploadedFile>  $attachments
     * @return array{0: Run, 1: AgentMessage, 2: array<int, Artifact>}
     */
    private function startTurn(AgentSession $session, string $message, string $triggerType, array $attachments, ?Skill $skill, ?string $replacing = null): array
    {
        $this->creditGate->assertCanStartRun($session->workspace);

        $run = $this->claimTurn($session, $message, $triggerType);

        try {
            // After the claim, so a turn still writing to these messages has
            // finished and a paused one has been closed out. Ids are
            // time-ordered, the same order the history is read in.
            if ($replacing !== null) {
                $session->messages()->where('id', '>=', $replacing)->delete();
            }

            $userMessage = $session->messages()->create([
                'role' => AgentMessageRole::User,
                'content' => $message,
                'skill_id' => $skill?->id,
            ]);

            // What `streamBegun()` needs to pick a queued turn up again.
            $run->forceFill(['input' => [...$run->input, 'user_message_id' => $userMessage->id]])->save();

            return [$run, $userMessage, $this->storeAttachments($session, $run, $userMessage, $attachments)];
        } catch (Throwable $e) {
            $this->failTurn($run, $e);

            throw $e;
        }
    }

    /**
     * The SDK agent for a turn whose `Run`, user message and attachments
     * already exist: instructions, tools and model from the pinned version.
     * Doesn't fail the run on an exception; the caller does.
     *
     * @param  array<int, Artifact>  $storedAttachments
     */
    private function buildTurn(AgentSession $session, Run $run, AgentMessage $userMessage, array $storedAttachments, ?Skill $skill): AgentTurn
    {
        // The version the conversation was started against, not whatever the
        // agent looks like right now: see `AgentSession::pinnedAgent()`.
        $agent = $session->pinnedAgent();

        $instructions = $this->autonomy->withNote($this->skillInjector->instructionsFor($agent, $run->triggered_by), $agent, $session);

        if ($skill !== null) {
            $instructions .= "\n\n".$this->skillInjector->chosenSkillSection($skill);
        }

        [$provider, $model] = $this->modelFor($agent, $run);
        $tools = $this->tools->toolsFor($agent, $run);

        $this->recordContext($run, $instructions, $provider, $model, $tools, $skill);

        return new AgentTurn(
            $session,
            $run,
            new WorkspaceAgent(
                $instructions,
                $session,
                $userMessage->id,
                $tools,
                GenerationSettings::fromAgent($agent),
            ),
            $provider,
            $model,
            array_map(fn (Artifact $artifact) => $artifact->toPromptAttachment(), $storedAttachments),
        );
    }

    /**
     * The agent's model, on the workspace's own provider key where it has
     * one for whoever started the run: see `ByokProviderRegistrar`.
     *
     * @return array{0: string|array<string, string>|null, 1: ?string}
     */
    private function modelFor(AgentModel $agent, Run $run): array
    {
        [$provider, $model] = $this->modelCatalog->forAgent($agent);

        return $this->byok->apply($provider, $model, $run->workspace_id, $run->triggered_by);
    }

    /**
     * Keeps what this turn is sent on its `Run`: see the `agent_context`
     * migration. A resumed turn is rebuilt from the same pinned version, so
     * the context recorded when it opened still describes it.
     *
     * @param  string|array<string, string>  $provider
     * @param  array<int, mixed>  $tools
     */
    private function recordContext(Run $run, string $instructions, string|array $provider, ?string $model, array $tools, ?Skill $skill = null): void
    {
        $run->forceFill(['agent_context' => [
            'provider' => $provider,
            'model' => $model,
            'skill' => $skill?->name,
            'instructions' => $instructions,
            'tools' => array_map(fn (mixed $tool): string => $tool instanceof Tool ? ToolNameResolver::resolve($tool) : class_basename($tool), $tools),
        ]])->save();
    }

    /**
     * Creates the turn's `Run`, refusing while another turn on the same
     * session is still in flight: two interleaved turns would each build
     * their context from a transcript the other is halfway through writing.
     * The lock only covers check-and-create; the `running` run itself is
     * what holds the session for the length of the turn.
     *
     * A turn still waiting on approvals doesn't block: writing again means
     * the person moved on, so its waiting calls are cancelled and the turn
     * closed out first (`abandonPausedTurns()`).
     */
    private function claimTurn(AgentSession $session, string $message, string $triggerType): Run
    {
        return Cache::lock("agent-session:{$session->id}:turn", 10)->block(5, function () use ($session, $message, $triggerType): Run {
            $this->abandonPausedTurns($session);

            $busy = $session->runs()
                ->where('status', RunStatus::Running)
                ->where('started_at', '>', now()->subMinutes(self::TURN_STALE_AFTER_MINUTES))
                ->exists();

            if ($busy) {
                throw RunStateException::sessionBusy();
            }

            $run = $session->runs()->create([
                'workspace_id' => $session->workspace_id,
                'trigger_type' => $triggerType,
                'input' => ['message' => $message],
                // Whose turn this is: tools act on this person's behalf (memories,
                // skill permissions, personal connector credentials).
                'triggered_by' => $session->user_id,
            ]);

            $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

            return $run;
        });
    }

    /**
     * Stores each attached file as an `Artifact` on `$userMessage`. Every
     * file starts its own artifact group (attaching `report.pdf` twice is
     * two attachments, not a second version of the first) and none is
     * indexed into the agent's shared knowledge (`searchable: false`).
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, Artifact>
     */
    private function storeAttachments(AgentSession $session, Run $run, AgentMessage $userMessage, array $files): array
    {
        if ($files === []) {
            return [];
        }

        $artifacts = array_map(fn (UploadedFile $file): Artifact => $this->storeArtifact->execute(
            workspace: $session->workspace,
            filename: $file->getClientOriginalName(),
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            contents: $file,
            agent: $session->agent,
            session: $session,
            run: $run,
            createdBy: $session->user_id,
            groupId: (string) Str::uuid(),
            message: $userMessage,
            searchable: false,
        ), $files);

        $run->forceFill([
            'input' => [...$run->input, 'attachment_ids' => array_map(fn (Artifact $artifact) => $artifact->id, $artifacts)],
        ])->save();

        return $artifacts;
    }

    /**
     * Closes out the provider's answer: a finished reply completes the turn,
     * one that stopped on calls needing approval pauses it.
     */
    private function settleTurn(AgentTurn $turn, AgentResponse $response): AgentMessage
    {
        $assistantMessage = $this->storeAssistantMessage($turn->session, $response, ResponseUsage::from($response, $turn->run->started_at));

        if ($response->hasPendingApprovals()) {
            return $this->pauseTurn($turn->session, $turn->run, $assistantMessage, $response);
        }

        return $this->completeTurn($turn->session, $turn->run, $assistantMessage);
    }

    private function completeTurn(AgentSession $session, Run $run, AgentMessage $assistantMessage): AgentMessage
    {
        $run->forceFill([
            'status' => RunStatus::Completed,
            // `message_id` lets `RecordRunCreditUsage` find the exact
            // `AgentMessage` to charge for, without guessing at "the
            // latest assistant message" for this session.
            'output' => ['text' => $assistantMessage->content, 'message_id' => $assistantMessage->id],
            'finished_at' => now(),
        ])->save();

        $session->forceFill(['last_activity_at' => now()])->save();

        event(new RunCompleted($run));

        $this->subagentTasks->complete($session, $assistantMessage);

        return $assistantMessage;
    }

    /**
     * Parks the turn until its waiting calls are decided: the reply so far
     * is kept with what resuming needs (`PausedTurn`), the calls are linked
     * to it, and the turn's `Run` waits in `awaiting_approval`, not
     * `running`, so it neither blocks the conversation nor gets failed as
     * stuck, and isn't billed until it finishes.
     */
    private function pauseTurn(AgentSession $session, Run $run, AgentMessage $assistantMessage, AgentResponse $response): AgentMessage
    {
        $assistantMessage->forceFill(['paused_state' => PausedTurn::stateFrom($response)])->save();

        $actions = $this->linkPendingActions($run, $session, $assistantMessage);

        $run->forceFill([
            'status' => RunStatus::AwaitingApproval,
            'output' => [
                'text' => $assistantMessage->content,
                'message_id' => $assistantMessage->id,
                'pending_action_ids' => $actions->modelKeys(),
            ],
        ])->save();

        $session->forceFill(['last_activity_at' => now()])->save();

        event(new AgentActionsRequested($session, $run, $actions));

        $this->subagentTasks->awaitApproval($session);

        return $assistantMessage;
    }

    /**
     * @return Collection<int, AgentAction>
     */
    private function linkPendingActions(Run $run, AgentSession $session, AgentMessage $assistantMessage): Collection
    {
        $actions = AgentAction::query()
            ->where('run_id', $run->id)
            ->where('agent_session_id', $session->id)
            ->whereIn('tool_call_id', $assistantMessage->paused_state['pending_tool_call_ids'] ?? [])
            ->get();

        AgentAction::query()->whereKey($actions->modelKeys())->update(['agent_message_id' => $assistantMessage->id]);

        return $actions->each(fn (AgentAction $action) => $action->setAttribute('agent_message_id', $assistantMessage->id));
    }

    /**
     * Claims a paused turn for resuming and settles its decided calls.
     * Claiming is a conditional update, so two resumes (a queued job and a
     * streamed one, say) can't both continue the same turn.
     */
    private function openResume(Run $run): Resumption
    {
        $claimed = Run::query()
            ->whereKey($run->id)
            ->where('status', RunStatus::AwaitingApproval)
            ->update(['status' => RunStatus::Running, 'started_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            throw RunStateException::notAwaitingApproval();
        }

        $run->refresh();

        try {
            $session = $run->runnable;

            if (! $session instanceof AgentSession) {
                throw new InvalidArgumentException("Run [{$run->id}] is not an agent conversation turn.");
            }

            $message = AgentMessage::query()->findOrFail($run->output['message_id'] ?? null);

            return $this->prepareResumption($session, $run, $message);
        } catch (Throwable $e) {
            $this->failTurn($run, $e);

            throw $e;
        }
    }

    /**
     * Everything a resumed turn needs, shared by a chat turn's resume and an
     * embedded Agent node's: the decided calls settled, the SDK agent built
     * to replay the paused message, and the decisions to hand it.
     */
    private function prepareResumption(AgentSession $session, Run $run, AgentMessage $message): Resumption
    {
        $agent = $session->pinnedAgent();

        $actions = $this->executor->pausedActions($message);

        if ($actions->contains(fn (AgentAction $action): bool => $action->status->isAwaitingDecision())) {
            throw RunStateException::approvalsUndecided();
        }

        $tools = $this->tools->toolsFor($agent, $run, $session);
        $settled = $this->executor->settle($actions, $tools);

        $instructions = $this->autonomy->withNote($this->skillInjector->instructionsFor($agent, $run->triggered_by), $agent, $session);
        [$provider, $model] = $this->modelFor($agent, $run);

        return new Resumption(
            new AgentTurn(
                $session,
                $run,
                new WorkspaceAgent($instructions, $session, null, $tools, GenerationSettings::fromAgent($agent), $message->id),
                $provider,
                $model,
            ),
            $message,
            $this->executor->decisionsFor($actions->each->refresh()),
            $settled,
            now(),
        );
    }

    private function settleResumedTurn(Resumption $resumption, AgentResponse $response): AgentMessage
    {
        $message = $this->mergeResumed($resumption, $response);
        $turn = $resumption->turn;

        if ($response->hasPendingApprovals()) {
            return $this->pauseTurn($turn->session, $turn->run, $message, $response);
        }

        event(new AgentActionsChanged($turn->session, $turn->run));

        return $this->completeTurn($turn->session, $turn->run, $message);
    }

    /**
     * Folds the resumed part of the turn into the message it paused on: the
     * settled calls' outcomes, any new calls and results, the rest of the
     * reply, and the combined usage. A result the SDK reports for a call
     * this turn already settled is ignored: the settled one is what really
     * happened.
     */
    private function mergeResumed(Resumption $resumption, AgentResponse $response): AgentMessage
    {
        $message = $resumption->message->fresh();

        $calls = collect($message->tool_calls ?? [])->keyBy('id');

        foreach ($response->toolCalls->toArray() as $call) {
            $calls->put($call['id'], $calls->get($call['id'], $call));
        }

        $results = collect($message->tool_results ?? [])->keyBy('id');

        foreach ($resumption->settledResults as $id => $result) {
            $results->put($id, $result);
        }

        foreach ($response->toolResults->toArray() as $result) {
            if (! $results->has($result['id'])) {
                $results->put($result['id'], $result);
            }
        }

        $content = collect([$message->content, $response->text])->map(fn (?string $text): string => trim((string) $text))->filter()->implode("\n\n");

        $message->forceFill([
            'content' => $content,
            'tool_calls' => $calls->isNotEmpty() ? $calls->values()->all() : null,
            'tool_results' => $results->isNotEmpty() ? $results->values()->all() : null,
            'paused_state' => null,
            'usage' => ResponseUsage::combine($message->usage ?? [], ResponseUsage::from($response, $resumption->startedAt)),
        ])->save();

        return $message;
    }

    /**
     * Closes out every turn in this conversation still waiting on approvals:
     * the waiting calls are cancelled and the turn completes with the reply
     * it had, billed for what it used. Called when a new message arrives:
     * the person has moved on.
     */
    public function abandonPausedTurns(AgentSession $session): void
    {
        $session->runs()
            ->where('status', RunStatus::AwaitingApproval)
            ->get()
            ->each(function (Run $run) use ($session): void {
                // Claimed like a resume (`openResume()`), so a turn a
                // decision is resuming right now is left to finish.
                $claimed = Run::query()
                    ->whereKey($run->id)
                    ->where('status', RunStatus::AwaitingApproval)
                    ->update(['status' => RunStatus::Running, 'updated_at' => now()]);

                if ($claimed === 0) {
                    return;
                }

                AgentAction::query()
                    ->where('run_id', $run->id)
                    ->where('status', AgentActionStatus::Pending)
                    ->update([
                        'status' => AgentActionStatus::Cancelled,
                        'decided_at' => now(),
                        'decision_note' => 'The conversation moved on before anyone decided.',
                        'updated_at' => now(),
                    ]);

                // Approved while others in the turn still waited: never run.
                AgentAction::query()
                    ->where('run_id', $run->id)
                    ->where('status', AgentActionStatus::Approved)
                    ->update(['status' => AgentActionStatus::Cancelled, 'updated_at' => now()]);

                $message = AgentMessage::query()->find($run->output['message_id'] ?? null);
                $message?->forceFill(['paused_state' => null])->save();

                event(new AgentActionsChanged($session, $run));

                if ($message !== null) {
                    $this->completeTurn($session, $run, $message);
                } else {
                    $run->forceFill(['status' => RunStatus::Cancelled, 'finished_at' => now()])->save();
                }
            });
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    private function storeAssistantMessage(AgentSession $session, AgentResponse $response, array $usage): AgentMessage
    {
        $message = $session->messages()->create([
            'role' => AgentMessageRole::Assistant,
            'content' => $response->text,
            'tool_calls' => $response->toolCalls->isNotEmpty() ? $response->toolCalls->toArray() : null,
            'tool_results' => $response->toolResults->isNotEmpty() ? $response->toolResults->toArray() : null,
        ]);
        $message->forceFill(['usage' => $usage])->save();

        return $message;
    }

    /**
     * Marks a turn's `Run` failed. Public because a streamed turn fails in
     * the caller's loop rather than inside this class: see `streamBegun()`.
     * Idempotent, so a caller that fails a turn the SDK already closed out
     * can't overwrite a completed run.
     */
    public function failTurn(Run $run, Throwable $e): void
    {
        if ($run->fresh()?->status->isTerminal()) {
            return;
        }

        $run->forceFill([
            'status' => RunStatus::Failed,
            'error' => $e->getMessage(),
            'finished_at' => now(),
        ])->save();

        event(new RunFailed($run));
    }

    /**
     * A single, stateless prompt against `$agent` with no history and no
     * conversation of its own: what `EvalRunner` calls for a graded eval
     * case, where each case is meant to be a fresh, independent turn.
     * `$run` is the *calling* run (an eval run's own `Run`, not a fresh
     * per-turn one), and is the execution context any attached tool call
     * executes against, same as a chat turn's own `Run` is for
     * `NodeTool::handle()`.
     *
     * `$simulateActions` simulates every call that would change something,
     * as Test run does, for runs nobody is watching, such as the eval runs
     * `RunEvalsOnAgentChangeJob` starts. `tool_calls` lists the names of the
     * tools the answer called, in order, so an eval can check them.
     *
     * @return array{text: string, usage: array<string, mixed>, tool_calls: array<int, string>}
     */
    public function ask(AgentModel $agent, Run $run, string $prompt, bool $simulateActions = false): array
    {
        $instructions = $this->skillInjector->instructionsFor($agent, $run->triggered_by);
        [$provider, $model] = $this->modelFor($agent, $run);

        $startedAt = now();

        $response = (new EmbeddedAgent($instructions, $this->tools->toolsFor($agent, $run, canPause: false, simulateActions: $simulateActions), GenerationSettings::fromAgent($agent)))
            ->prompt($prompt, provider: $provider, model: $model);

        return [
            'text' => $response->text,
            'usage' => ResponseUsage::from($response, $startedAt),
            'tool_calls' => $response->toolCalls->pluck('name')->values()->all(),
        ];
    }

    /**
     * What `Nodes\AiAutomation\AgentNode` calls during a workflow run: see
     * docs/gumloop/output/raw/core-concepts/agent_node.md's "Node Inputs"/
     * "Node Outputs"/"Continuing Conversations" sections. Unlike `ask()`,
     * this turn is persisted as a real `AgentSession`/`AgentMessage` pair so
     * a later Agent-node call can continue it via `$previousConversationId`:
     * started fresh when that's null, otherwise loaded and validated
     * against `$agent` and `$run`'s workspace.
     *
     * Deliberately creates no `Run` of its own and never fires
     * `RunCompleted`: this call's tokens are already billed as part of the
     * calling workflow node's own `NodeRun.usage` (`CreditMeter::costForNodeRun`):
     * billing them again under `AgentStep` would double-charge the same
     * tokens. `AgentMessage.usage` is still recorded, purely so the turn's
     * cost is visible when inspecting the conversation later.
     *
     * An action that needs approval pauses the conversation just as in a
     * chat, then throws `AgentTurnPausedException` so the workflow engine can
     * park the Agent node (`WorkflowRunner`); `resumeInConversation()` picks
     * it up once decided.
     *
     * @return array{
     *     text: string,
     *     usage: array<string, mixed>,
     *     conversation_id: string,
     *     messages: array<int, array{role: string, content: string, tool_calls: array<int, mixed>|null}>,
     *     attachment_names: string,
     * }
     *
     * @throws AgentTurnPausedException
     */
    public function askInConversation(AgentModel $agent, Run $run, string $prompt, ?string $previousConversationId): array
    {
        $session = $previousConversationId === null
            ? $this->createSession->execute($agent, $run->triggeredBy)
            : $this->conversationFor($agent, $run, $previousConversationId);

        $instructions = $this->autonomy->withNote($this->skillInjector->instructionsFor($agent, $run->triggered_by), $agent, $session);
        [$provider, $model] = $this->modelFor($agent, $run);

        $userMessage = $session->messages()->create([
            'role' => AgentMessageRole::User,
            'content' => $prompt,
        ]);

        $startedAt = now();

        $response = (new WorkspaceAgent($instructions, $session, $userMessage->id, $this->tools->toolsFor($agent, $run, $session), GenerationSettings::fromAgent($agent)))
            ->prompt($prompt, provider: $provider, model: $model);

        $usage = ResponseUsage::from($response, $startedAt);

        $assistantMessage = $this->storeAssistantMessage($session, $response, $usage);

        $session->forceFill(['last_activity_at' => now()])->save();

        if ($response->hasPendingApprovals()) {
            $this->pauseInConversation($session, $run, $assistantMessage, $response);
        }

        return $this->conversationResult($session, $run, $assistantMessage);
    }

    /**
     * Continues an Agent node's conversation that paused for approvals:
     * the embedded counterpart of `resume()`. `$run` is the workflow run
     * the node belongs to, which the calls ran against. Throws
     * `AgentTurnPausedException` again if the agent stops on another call
     * that needs approval.
     *
     * @return array{text: string, usage: array<string, mixed>, conversation_id: string, messages: array<int, array{role: string, content: string, tool_calls: array<int, mixed>|null}>, attachment_names: string}
     *
     * @throws AgentTurnPausedException
     */
    public function resumeInConversation(Run $run, AgentSession $session, AgentMessage $message): array
    {
        $resumption = $this->prepareResumption($session, $run, $message);

        $response = $resumption->turn->agent->prompt($resumption->decisions, provider: $resumption->turn->provider, model: $resumption->turn->model);

        $message = $this->mergeResumed($resumption, $response);

        $session->forceFill(['last_activity_at' => now()])->save();

        if ($response->hasPendingApprovals()) {
            $this->pauseInConversation($session, $run, $message, $response);
        }

        event(new AgentActionsChanged($session, $run));

        return $this->conversationResult($session, $run, $message);
    }

    /**
     * @throws AgentTurnPausedException
     */
    private function pauseInConversation(AgentSession $session, Run $run, AgentMessage $assistantMessage, AgentResponse $response): never
    {
        $assistantMessage->forceFill(['paused_state' => PausedTurn::stateFrom($response)])->save();

        $actions = $this->linkPendingActions($run, $session, $assistantMessage);

        event(new AgentActionsRequested($session, $run, $actions));

        throw new AgentTurnPausedException($session, $assistantMessage);
    }

    /**
     * @return array{text: string, usage: array<string, mixed>, conversation_id: string, messages: array<int, array{role: string, content: string, tool_calls: array<int, mixed>|null}>, attachment_names: string}
     */
    private function conversationResult(AgentSession $session, Run $run, AgentMessage $assistantMessage): array
    {
        return [
            'text' => (string) $assistantMessage->content,
            'usage' => $assistantMessage->usage ?? [],
            'conversation_id' => $session->id,
            'messages' => $session->messages()->oldest()->get()
                ->map(fn (AgentMessage $message): array => [
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'tool_calls' => $message->tool_calls,
                ])
                ->all(),
            'attachment_names' => Artifact::query()
                ->where('run_id', $run->id)
                ->where('agent_session_id', $session->id)
                ->pluck('filename')
                ->implode(','),
        ];
    }

    /**
     * Loads and validates a conversation to continue: it must exist, in
     * the calling run's own workspace, for this same `$agent`. A session
     * belongs to exactly one agent (`AgentSession::pinnedAgent()`), so
     * continuing it with a different agent selected on the node wouldn't
     * mean anything; Gumloop's own doc calls the equivalent failure
     * "Conversation Not Found".
     */
    private function conversationFor(AgentModel $agent, Run $run, string $previousConversationId): AgentSession
    {
        $session = AgentSession::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('agent_id', $agent->id)
            ->find($previousConversationId);

        if ($session === null) {
            throw new InvalidArgumentException("Conversation [{$previousConversationId}] not found for agent [{$agent->id}] in this workspace.");
        }

        return $session;
    }
}
