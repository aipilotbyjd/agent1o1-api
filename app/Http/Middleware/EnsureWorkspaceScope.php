<?php

namespace App\Http\Middleware;

use App\Authorization\WorkspaceContext;
use App\Models\Workspaces\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceScope
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        abort_unless($workspace instanceof Workspace, 404);

        abort_if($workspace->isSuspended(), 403, 'This workspace has been suspended.');

        $role = WorkspaceContext::resolveRole($workspace, $request->user());

        abort_if($role === null, 403, 'You are not a member of this workspace.');

        app()->instance(WorkspaceContext::class, new WorkspaceContext($workspace, $role));

        return $next($request);
    }
}
