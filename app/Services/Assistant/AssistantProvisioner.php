<?php

namespace App\Services\Assistant;

use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * Gives a workspace member their personal assistant the first time they
 * open it: there is no "create assistant" action. Safe to call on every
 * request: two concurrent first opens settle on the same row.
 */
class AssistantProvisioner
{
    public function forMember(Workspace $workspace, User $user): Assistant
    {
        $existing = $this->find($workspace, $user);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return Assistant::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'settings' => [],
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->find($workspace, $user) ?? throw new LogicException('Assistant vanished after a unique violation.');
        }
    }

    private function find(Workspace $workspace, User $user): ?Assistant
    {
        return Assistant::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
