<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WorkflowBuilderSession;

class WorkflowBuilderSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WorkflowView->value);
    }

    public function view(User $user, WorkflowBuilderSession $session): bool
    {
        return $session->isOwnedBy($user);
    }

    public function update(User $user, WorkflowBuilderSession $session): bool
    {
        return $session->isOwnedBy($user) && $session->isActive();
    }

    public function delete(User $user, WorkflowBuilderSession $session): bool
    {
        return $session->isOwnedBy($user);
    }

    public function message(User $user, WorkflowBuilderSession $session): bool
    {
        return $session->isOwnedBy($user) && $session->isActive();
    }

    public function save(User $user, WorkflowBuilderSession $session): bool
    {
        return $session->isOwnedBy($user)
            && $user->can(Permission::WorkflowCreate->value);
    }

    public function restore(User $user, WorkflowBuilderSession $session): bool
    {
        return $session->isOwnedBy($user) && $session->isActive();
    }
}
