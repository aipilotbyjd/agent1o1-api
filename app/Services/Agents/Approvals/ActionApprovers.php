<?php

namespace App\Services\Agents\Approvals;

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Models\Agents\AgentAction;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Collection;

/**
 * Who may decide an action, and who is told it's waiting.
 *
 * A tool rule can name its approvers (`user:<id>`, `role:<role>`); then only
 * they decide, plus the workspace's owners and admins, who could change the
 * rule anyway. Without a list: the person the conversation belongs to, and
 * anyone whose role grants `agent.approve`.
 */
class ActionApprovers
{
    public function canDecide(User $user, AgentAction $action): bool
    {
        $workspace = $action->workspace;
        $role = WorkspaceContext::resolveRole($workspace, $user);

        if ($role === null) {
            return false;
        }

        if (in_array($role, [Role::Owner, Role::Admin], true)) {
            return true;
        }

        $approvers = $action->approvers ?? [];

        if ($approvers !== []) {
            return in_array("user:{$user->id}", $approvers, true) || in_array("role:{$role->value}", $approvers, true);
        }

        return $action->session?->user_id === $user->id || $role->has(Permission::AgentApprove);
    }

    /**
     * Everyone who should hear about at least one of these actions waiting.
     *
     * @param  Collection<int, AgentAction>  $actions
     * @return Collection<int, User>
     */
    public function recipientsFor(Workspace $workspace, Collection $actions): Collection
    {
        return $this->notificationsFor($workspace, $actions)->map(fn (array $entry): User => $entry['user'])->values();
    }

    /**
     * Each person to notify with only the actions *they* are to hear about:
     * a mixed batch must not show one action's arguments, reason or signed
     * link to someone who may not decide it.
     *
     * @param  Collection<int, AgentAction>  $actions
     * @return Collection<int, array{user: User, actions: Collection<int, AgentAction>}>
     */
    public function notificationsFor(Workspace $workspace, Collection $actions): Collection
    {
        $members = $workspace->users()
            ->whereNull((new WorkspaceMember)->qualifyColumn('deleted_at'))
            ->get();

        return $members
            ->map(function (User $user) use ($workspace, $actions): ?array {
                $role = $workspace->owner_id === $user->id ? Role::Owner : Role::tryFrom((string) $user->pivot?->role);

                if ($role === null) {
                    return null;
                }

                $visible = $actions->filter(fn (AgentAction $action): bool => $this->shouldHear($user, $role, $action))->values();

                return $visible->isEmpty() ? null : ['user' => $user, 'actions' => $visible];
            })
            ->filter()
            ->values();
    }

    private function shouldHear(User $user, Role $role, AgentAction $action): bool
    {
        if (in_array($role, [Role::Owner, Role::Admin], true)) {
            return true;
        }

        $approvers = $action->approvers ?? [];

        if ($approvers !== []) {
            return in_array("user:{$user->id}", $approvers, true) || in_array("role:{$role->value}", $approvers, true);
        }

        return $action->session?->user_id === $user->id;
    }
}
