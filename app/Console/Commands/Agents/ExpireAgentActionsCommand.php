<?php

namespace App\Console\Commands\Agents;

use App\Enums\Agents\AgentActionStatus;
use App\Events\Agents\AgentActionsChanged;
use App\Models\Agents\AgentAction;
use App\Services\Agents\Approvals\ActionResumer;
use Illuminate\Console\Command;

/**
 * Expires waiting actions nobody decided before `expires_at` (the
 * workspace's `approval_ttl_minutes`). An expired action counts as rejected
 * with an explanation, so its turn resumes and the agent can tell the user
 * it didn't go ahead: a paused turn never waits forever.
 */
class ExpireAgentActionsCommand extends Command
{
    protected $signature = 'agents:expire-actions';

    protected $description = 'Expire agent actions nobody approved in time and resume their turns.';

    public function handle(ActionResumer $resumer): int
    {
        $due = AgentAction::query()
            ->with(['session', 'run'])
            ->where('status', AgentActionStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        // Expired only while still pending: a person deciding at the same
        // moment wins, rather than having their decision overwritten.
        $expired = $due->filter(fn (AgentAction $action): bool => AgentAction::query()
            ->whereKey($action->id)
            ->where('status', AgentActionStatus::Pending)
            ->update([
                'status' => AgentActionStatus::Expired,
                'decided_at' => now(),
                'decision_channel' => 'expiry',
                'updated_at' => now(),
            ]) === 1)
            ->each(fn (AgentAction $action) => $action->forceFill(['status' => AgentActionStatus::Expired, 'decision_channel' => 'expiry'])->syncOriginal())
            ->values();

        $expired->unique('agent_session_id')->each(function (AgentAction $action): void {
            if ($action->session !== null) {
                event(new AgentActionsChanged($action->session, $action->run));
            }
        });

        $resumer->resumeReady($expired);

        $this->info("Expired {$expired->count()} action(s).");

        return self::SUCCESS;
    }
}
