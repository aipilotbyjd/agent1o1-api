<?php

namespace App\Authorization;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Plan;
use App\Models\Workspace;

final class WorkspaceContext
{
    public ?Workspace $workspace = null;

    public ?Role $role = null;

    public ?Plan $plan = null;

    public function allows(Permission $permission): bool
    {
        if (! $this->role || ! in_array($permission, $this->role->permissions(), true)) {
            return false;
        }

        $feature = FeatureGates::map()[$permission->value] ?? null;

        return $feature === null || ($this->plan?->hasFeature($feature) ?? false);
    }
}
