<?php

namespace App\Traits;

use App\Models\Credential;
use App\Models\Workspace;
use Illuminate\Http\Request;

trait ResolvesConnectedNodes
{
    private function loadConnectedTypes(string $workspaceId): void
    {
        $types = Credential::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('type')
            ->mapWithKeys(fn ($t) => [$t => true])
            ->all();

        request()->attributes->set('node_connected_types', $types);
    }

    private function resolveWorkspaceForConnected(Request $request): ?Workspace
    {
        if (! $request->filled('workspace_id')) {
            return null;
        }

        $workspace = Workspace::find($request->input('workspace_id'));

        if (! $workspace) {
            abort(404, 'Workspace not found.');
        }

        $user = $request->user();

        if ($workspace->owner_id !== $user->id) {
            $isMember = $workspace->members()->where('user_id', $user->id)->exists();
            if (! $isMember) {
                abort(403, 'You are not a member of this workspace.');
            }
        }

        return $workspace;
    }
}
