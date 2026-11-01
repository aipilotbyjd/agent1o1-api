<?php

namespace App\Services\Assistant\Channels;

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Permission;
use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\AssistantProvisioner;
use Illuminate\Support\Str;

/**
 * Who is writing from outside the app, and which of their assistants
 * answers. Only verified accounts, and only where their role allows the
 * assistant.
 */
class ChannelUsers
{
    public function __construct(private readonly AssistantProvisioner $provisioner) {}

    public function byEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [Str::lower(trim($email))])
            ->whereNotNull('email_verified_at')
            ->first();
    }

    /**
     * In `$workspace` when given (a Slack install belongs to one), else the
     * user's current workspace, else the first one that allows it.
     */
    public function assistantFor(User $user, ?Workspace $workspace = null): ?Assistant
    {
        $candidates = $workspace !== null
            ? collect([$workspace])
            : collect([$user->currentWorkspace])->filter()->concat($user->workspaces()->get());

        $chosen = $candidates
            ->unique('id')
            ->first(fn (Workspace $candidate): bool => WorkspaceContext::resolveRole($candidate, $user)?->has(Permission::AssistantUse) ?? false);

        return $chosen === null ? null : $this->provisioner->forMember($chosen, $user);
    }
}
