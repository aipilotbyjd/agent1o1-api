<?php

namespace App\Http\Middleware;

use App\Authorization\WorkspaceContext;
use App\Enums\Role;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspaceContext
{
    public function __construct(private WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        if (! $workspace instanceof Workspace) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Owner short-circuit — zero pivot queries
        if ($workspace->owner_id === $user->id) {
            $role = Role::Owner;
            $request->attributes->set('workspace_role', $role->value);
            $this->populateContext($workspace, $role);

            return $next($request);
        }

        $member = $workspace->members()
            ->where('users.id', $user->id)
            ->first();

        if (! $member) {
            return response()->json(['message' => 'You are not a member of this workspace.'], 403);
        }

        $role = $member->pivot->role ?? Role::Viewer;
        $request->attributes->set('workspace_role', $role->value);
        $this->populateContext($workspace, $role);

        return $next($request);
    }

    private function populateContext(Workspace $workspace, Role $role): void
    {
        $this->context->workspace = $workspace;
        $this->context->role = $role;
        $this->context->plan = $workspace->currentPlan();
    }
}
