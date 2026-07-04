<?php

namespace App\Observers;

use App\Models\Agent;
use App\Services\ActivityLogService;

class AgentObserver
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function created(Agent $agent): void
    {
        $this->activity->log($agent->workspace_id, 'agent.created', $agent, "Created agent “{$agent->name}”.");
    }

    public function updated(Agent $agent): void
    {
        $this->activity->log($agent->workspace_id, 'agent.updated', $agent, "Updated agent “{$agent->name}”.", [
            'changed' => array_keys($agent->getChanges()),
        ]);
    }

    public function deleted(Agent $agent): void
    {
        $this->activity->log($agent->workspace_id, 'agent.deleted', $agent, "Deleted agent “{$agent->name}”.");
    }
}
