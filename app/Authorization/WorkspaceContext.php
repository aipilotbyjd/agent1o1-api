<?php

namespace App\Authorization;

use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;

final readonly class WorkspaceContext
{
    public function __construct(
        public Workspace $workspace,
        public ?Role $role,
    ) {}

    public function allows(Permission $permission): bool
    {
        return $this->role?->has($permission) ?? false;
    }

    public static function resolveRole(Workspace $workspace, User $user): ?Role
    {
        if ($workspace->owner_id === $user->id) {
            return Role::Owner;
        }

        $contextKey = self::contextKey($workspace->id, $user->id);

        if (Context::hasHidden($contextKey)) {
            return Context::getHidden($contextKey);
        }

        $raw = Cache::remember(
            self::cacheKey($workspace->id, $user->id),
            now()->addMinutes(5),
            fn () => $workspace->members()->where('user_id', $user->id)->toBase()->value('role') ?? '__none__',
        );

        $role = $raw === '__none__' ? null : Role::from($raw);

        Context::addHidden($contextKey, $role);

        return $role;
    }

    public static function forget(string $workspaceId, string $userId): void
    {
        Cache::forget(self::cacheKey($workspaceId, $userId));
        Context::forgetHidden(self::contextKey($workspaceId, $userId));
    }

    private static function cacheKey(string $workspaceId, string $userId): string
    {
        return "workspace:{$workspaceId}:member:{$userId}:role";
    }

    private static function contextKey(string $workspaceId, string $userId): string
    {
        return "workspace:{$workspaceId}:user:{$userId}:role";
    }
}
