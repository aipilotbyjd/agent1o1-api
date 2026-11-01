<?php

namespace App\Broadcasting;

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Permission;
use App\Models\Agents\AgentSession;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\Storage;

/**
 * Who may subscribe to what. Lives here rather than as closures inside
 * `routes/channels.php` so the rules can be unit-tested directly, the way
 * every other authorization decision in the app can be.
 *
 * The rule throughout: a channel requires the same workspace permission as
 * the REST endpoint carrying the same data. Someone who may not read a run
 * over HTTP must not be able to watch it arrive over a socket instead.
 */
class WorkspaceChannelGate
{
    public function runs(User $user, string $workspaceId): bool
    {
        return $this->allows($user, $workspaceId, Permission::RunView);
    }

    public function run(User $user, string $workspaceId, string $runId): bool
    {
        if (! $this->allows($user, $workspaceId, Permission::RunView)) {
            return false;
        }

        // Belt and braces: a member of workspace A must not be able to watch
        // a run living in workspace B by splicing its id into A's channel
        // name. The same check `ensureBelongsToWorkspace()` makes over HTTP.
        return Run::whereKey($runId)->where('workspace_id', $workspaceId)->exists();
    }

    public function agentSession(User $user, string $workspaceId, string $sessionId): bool
    {
        if (! $this->allows($user, $workspaceId, Permission::AgentView)) {
            return false;
        }

        return AgentSession::whereKey($sessionId)->where('workspace_id', $workspaceId)->exists();
    }

    public function workflowBuilderSession(User $user, string $workspaceId, string $sessionId): bool
    {
        if (! $this->allows($user, $workspaceId, Permission::WorkflowBuilderUse)) {
            return false;
        }

        return WorkflowBuilderSession::whereKey($sessionId)->where('workspace_id', $workspaceId)->exists();
    }

    public function assistantSession(User $user, string $workspaceId, string $sessionId): bool
    {
        if (! $this->allows($user, $workspaceId, Permission::AssistantUse)) {
            return false;
        }

        return AssistantSession::query()
            ->whereKey($sessionId)
            ->whereHas('assistant', fn ($query) => $query->where('workspace_id', $workspaceId)->where('user_id', $user->id))
            ->exists();
    }

    public function assistant(User $user, string $workspaceId, string $assistantId): bool
    {
        if (! $this->allows($user, $workspaceId, Permission::AssistantUse)) {
            return false;
        }

        return Assistant::query()->whereKey($assistantId)->where('workspace_id', $workspaceId)->where('user_id', $user->id)->exists();
    }

    public function page(User $user, string $workspaceId, string $pageId): bool
    {
        if (! $this->allows($user, $workspaceId, Permission::PageView)) {
            return false;
        }

        return WorkflowPage::whereKey($pageId)->where('workspace_id', $workspaceId)->exists();
    }

    /**
     * Joining a workflow's presence channel shows the person to everyone else
     * who has it open, so it takes the same permission as opening the workflow.
     *
     * @return array{id: string, name: string, avatar: string|null}|false
     */
    public function workflowPresence(User $user, string $workspaceId, string $workflowId): array|false
    {
        if (! $this->allows($user, $workspaceId, Permission::WorkflowView)) {
            return false;
        }

        if (! Workflow::whereKey($workflowId)->where('workspace_id', $workspaceId)->exists()) {
            return false;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
        ];
    }

    private function allows(User $user, string $workspaceId, Permission $permission): bool
    {
        $workspace = Workspace::find($workspaceId);

        if ($workspace === null) {
            return false;
        }

        return WorkspaceContext::resolveRole($workspace, $user)?->has($permission) ?? false;
    }
}
