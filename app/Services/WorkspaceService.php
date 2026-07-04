<?php

namespace App\Services;

use App\Enums\Role;
use App\Events\WorkspaceCreated;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    public function create(User $owner, array $data): Workspace
    {
        $workspace = $this->retryOnSlugCollision(fn () => Workspace::create([
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($data['name']),
            'owner_id' => $owner->id,
        ]));

        // Invariant: owner_id holder always has a pivot row with role=owner.
        // Only this method and transferOwnership() may write owner roles.
        $workspace->members()->attach($owner->id, [
            'id' => Str::uuid()->toString(),
            'role' => Role::Owner,
            'joined_at' => now(),
        ]);

        if (! $owner->current_workspace_id) {
            $owner->update(['current_workspace_id' => $workspace->id]);
        }

        event(new WorkspaceCreated($workspace));

        return $workspace;
    }

    public function update(Workspace $workspace, array $data): Workspace
    {
        if (isset($data['name']) && $data['name'] !== $workspace->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $workspace->id);
        }

        $workspace->update($data);

        return $workspace->fresh();
    }

    public function delete(Workspace $workspace, User $deleter): void
    {
        $workspace->delete();

        if ($deleter->current_workspace_id === $workspace->id) {
            $this->fallbackCurrentWorkspace($deleter);
        }
    }

    public function transferOwnership(Workspace $workspace, User $newOwner): Workspace
    {
        $oldOwner = User::findOrFail($workspace->owner_id);

        DB::transaction(function () use ($workspace, $newOwner, $oldOwner) {
            $workspace->update(['owner_id' => $newOwner->id]);

            // Demote old owner to admin
            $workspace->members()->updateExistingPivot($oldOwner->id, ['role' => Role::Admin]);

            // Ensure new owner has owner pivot row
            $existing = $workspace->members()->where('users.id', $newOwner->id)->first();
            if ($existing) {
                $workspace->members()->updateExistingPivot($newOwner->id, ['role' => Role::Owner]);
            } else {
                $workspace->members()->attach($newOwner->id, [
                    'id' => Str::uuid()->toString(),
                    'role' => Role::Owner,
                    'joined_at' => now(),
                ]);
            }
        });

        return $workspace->fresh();
    }

    public function removeMember(Workspace $workspace, User $user, User $remover): void
    {
        $workspace->members()->detach($user->id);

        if ($user->current_workspace_id === $workspace->id) {
            $this->fallbackCurrentWorkspace($user);
        }
    }

    public function leave(Workspace $workspace, User $user): void
    {
        $workspace->members()->detach($user->id);

        if ($user->current_workspace_id === $workspace->id) {
            $this->fallbackCurrentWorkspace($user);
        }
    }

    private function fallbackCurrentWorkspace(User $user): void
    {
        $other = $user->workspaces()->first();
        $user->update(['current_workspace_id' => $other?->id]);
    }

    private function generateUniqueSlug(string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Workspace::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function retryOnSlugCollision(callable $callback, int $maxAttempts = 3): Workspace
    {
        $attempts = 0;
        while (true) {
            try {
                return $callback();
            } catch (QueryException $e) {
                $attempts++;
                if ($attempts >= $maxAttempts || ! str_contains($e->getMessage(), 'slug')) {
                    throw $e;
                }
            }
        }
    }
}
