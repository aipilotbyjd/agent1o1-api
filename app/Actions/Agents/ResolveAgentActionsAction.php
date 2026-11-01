<?php

namespace App\Actions\Agents;

use App\Authorization\WorkspaceContext;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\Workspaces\Permission;
use App\Events\Agents\AgentActionsChanged;
use App\Models\Agents\AgentAction;
use App\Models\User;
use App\Services\Agents\Approvals\ActionApprovers;
use App\Services\Agents\Approvals\ActionResumer;
use App\Services\Agents\Approvals\TrustRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records people's decisions on waiting actions, then resumes every turn
 * they leave fully decided (unless the caller will resume one itself, as
 * the streaming endpoint does).
 *
 * Each decision is one of:
 * - `approve`: run it as the model asked.
 * - `edit`: run it with `arguments` instead. The edit is still filtered by
 *   the tool: a field bound at attach time can't be changed this way.
 * - `reject`: don't run it. With `stop`, the agent's turn ends there;
 *   otherwise it carries on, told of the rejection and any `note`.
 *
 * `remember` on an approval also sets the tool to run without asking from
 * now on, only for someone who may manage the agent.
 *
 * Idempotent: an action that is no longer waiting is skipped, not
 * re-decided, so a double click or two people answering at once is safe.
 */
class ResolveAgentActionsAction
{
    public function __construct(
        private readonly ActionApprovers $approvers,
        private readonly ActionResumer $resumer,
        private readonly TrustRules $trust,
    ) {}

    /**
     * @param  list<array{action_id: string, decision: string, arguments?: array<string, mixed>|null, note?: string|null, stop?: bool, remember?: bool}>  $decisions
     * @param  User|null  $user  null for a decision made on a person's behalf without an account here (a chat button)
     * @return Collection<int, AgentAction> the actions this call decided
     *
     * @throws AuthorizationException
     */
    public function execute(?User $user, array $decisions, string $channel = 'app', bool $resume = true, ?string $actorLabel = null): Collection
    {
        $decided = DB::transaction(function () use ($user, $decisions, $channel, $actorLabel): Collection {
            $decided = new Collection;

            foreach ($decisions as $decision) {
                $action = AgentAction::query()->with(['session', 'workspace', 'agent'])->lockForUpdate()->find($decision['action_id']);

                if ($action === null || ! $action->status->isAwaitingDecision()) {
                    continue;
                }

                if ($user !== null && ! $this->approvers->canDecide($user, $action)) {
                    throw new AuthorizationException('You are not allowed to decide this action.');
                }

                $this->apply($action, $decision, $user, $channel, $actorLabel);

                if (($decision['remember'] ?? false) && $decision['decision'] !== 'reject' && $user !== null
                    && WorkspaceContext::resolveRole($action->workspace, $user)?->has(Permission::AgentManage)) {
                    $this->trust->allowAlways($action->agent, $action->tool_name);
                }

                $decided->push($action);
            }

            return $decided;
        });

        $decided->unique('agent_session_id')->each(function (AgentAction $action): void {
            if ($action->session !== null) {
                event(new AgentActionsChanged($action->session, $action->run));
            }
        });

        if ($resume) {
            $this->resumer->resumeReady($decided);
        }

        return $decided;
    }

    /**
     * @param  array{decision: string, arguments?: array<string, mixed>|null, note?: string|null, stop?: bool}  $decision
     */
    private function apply(AgentAction $action, array $decision, ?User $user, string $channel, ?string $actorLabel): void
    {
        $note = filled($decision['note'] ?? null) ? trim((string) $decision['note']) : null;

        $action->forceFill([
            'status' => $decision['decision'] === 'reject' ? AgentActionStatus::Rejected : AgentActionStatus::Approved,
            'edited_arguments' => $decision['decision'] === 'edit' ? ($decision['arguments'] ?? []) : null,
            'stops_turn' => $decision['decision'] === 'reject' && ($decision['stop'] ?? false),
            'decided_by' => $user?->id,
            'decided_at' => now(),
            'decision_note' => $actorLabel !== null ? trim("{$note} ({$actorLabel})") : $note,
            'decision_channel' => $channel,
        ])->save();
    }
}
