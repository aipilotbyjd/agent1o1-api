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

    protected function ensureBelongsToWorkspace(Workspace $workspace, Model $model): void
    {
        abort_if($model->workspace_id !== $workspace->id, 404);
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
