<?php

namespace App\Services\Workspaces;

use App\Enums\Triggers\TriggerType;
use App\Enums\Workspaces\Role;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\Policies\FreeCreditPolicy;
use App\Services\Timezones\Timezones;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    /**
     * @param  array{name: string, avatar?: string|null, timezone?: string}  $data
     */
    public function create(User $owner, array $data): Workspace
    {
        return DB::transaction(function () use ($owner, $data) {
            $workspace = Workspace::query()->create([
                'name' => $data['name'],
                'slug' => $this->suggestSlug($data['name']),
                'avatar' => $data['avatar'] ?? null,
                'timezone' => $data['timezone'] ?? Timezones::DEFAULT,
                'owner_id' => $owner->id,
            ]);

            $workspace->members()->create([
                'user_id' => $owner->id,
                'role' => Role::Owner,
                'joined_at' => now(),
            ]);

            $owner->update(['current_workspace_id' => $workspace->id]);

            app(FreeCreditPolicy::class)->claimFreeWorkspace($owner, $workspace);

            return $workspace;
        });
    }

    public function switchTo(User $user, Workspace $workspace): void
    {
        if (! $workspace->members()->where('user_id', $user->id)->exists()) {
            throw new AuthorizationException('You are not a member of this workspace.');
        }

        $user->update(['current_workspace_id' => $workspace->id]);
    }

    /**
     * @param  array{name?: string, timezone?: string}  $data
     */
    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        if ($workspace->wasChanged('timezone')) {
            $this->rescheduleTriggers($workspace);
        }

        return $workspace;
    }

    /**
     * Move every schedule that runs in the workspace timezone, having none of
     * its own, onto the new one.
     */
    private function rescheduleTriggers(Workspace $workspace): void
    {
        $workspace->triggers()
            ->where('type', TriggerType::Schedule)
            ->each(function (Trigger $trigger) use ($workspace): void {
                if (filled($trigger->config['timezone'] ?? null)) {
                    return;
                }

                $trigger->setRelation('workspace', $workspace);
                $trigger->forceFill(['next_run_at' => $trigger->nextRun(now(), allowCurrentMinute: true)])->save();
            });
    }

    public function updateAvatar(Workspace $workspace, string $path): Workspace
    {
        $workspace->update(['avatar' => $path]);

        return $workspace;
    }

    public function delete(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace) {
            $workspace->members()->delete();
            $workspace->invitations()->delete();
            $workspace->delete();
        });
    }

    public function updateMemberRole(Workspace $workspace, User $member, Role $role): void
    {
        $workspaceMember = $workspace->members()->where('user_id', $member->id)->firstOrFail();

        if ($workspaceMember->role === Role::Owner) {
            throw new AuthorizationException("The workspace owner's role cannot be changed.");
        }

        $workspaceMember->update(['role' => $role]);
    }

    public function removeMember(Workspace $workspace, User $member): void
    {
        $workspaceMember = $workspace->members()->where('user_id', $member->id)->firstOrFail();

        if ($workspaceMember->role === Role::Owner) {
            throw new AuthorizationException('The workspace owner cannot be removed.');
        }

        $workspaceMember->delete();
    }

    public function leave(Workspace $workspace, User $user): void
    {
        if ($workspace->owner_id === $user->id) {
            throw new AuthorizationException('Transfer ownership before leaving this workspace.');
        }

        // Per model, not a bulk delete, so the member's observers (cache + audit) run.
        $workspace->members()->where('user_id', $user->id)->get()->each->delete();
    }

    public function suggestSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Workspace::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
