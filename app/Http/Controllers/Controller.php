<?php

namespace App\Http\Controllers;

use App\Enums\Workspaces\Permission;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    protected function requirePermission(Permission $permission): void
    {
        $this->authorize($permission->value);
    }

    /**
     * The second layer behind scoped route bindings: routes under
     * `/workspaces/{workspace}` already find their models *through* the
     * workspace and 404 on a stranger's, so this only catches a route left
     * out of that (templates, the assistant) or a model not bound from the URL.
     * Keep it where it is; don't rely on it alone for a new route.
     */
    protected function ensureBelongsToWorkspace(Workspace $workspace, Model $model): void
    {
        abort_if($model->workspace_id !== $workspace->id, 404);
    }

    /**
     * The `per_page` query parameter, clamped to 1..$max so a caller can't
     * ask one request to load a whole table.
     */
    protected function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        return max(1, min($request->integer('per_page', $default), $max));
    }

    /**
     * The workspace an API key request is scoped to, put there by
     * `EnsureApiKeyIsValid`. Public-API controllers have no workspace route
     * parameter and no authenticated user: the key *is* the scope.
     */
    protected function apiKeyWorkspace(Request $request): Workspace
    {
        $workspace = $request->attributes->get('workspace');

        abort_unless($workspace instanceof Workspace, 401, 'This API key is not bound to a workspace.');

        return $workspace;
    }
}
