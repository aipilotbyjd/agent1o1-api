<?php

namespace App\Models\Concerns;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Personal/Team visibility and one-default-per-group for a stored
 * credential (`scope`, `is_default`, `created_by`, `workspace_id`). A team
 * credential is visible to every member allowed to see the resource; a
 * personal one only to whoever created it: hidden from every other
 * member regardless of role, including owners/admins.
 *
 * The model says which column completes its default group: the connector
 * for a `ConnectorCredential`, the provider for an `AiProviderCredential`.
 */
trait HasScopedVisibility
{
    abstract protected function defaultGroupColumn(): string;

    public function isVisibleTo(User $user): bool
    {
        return $this->scope === ConnectorCredentialScope::Team || $this->created_by === $user->id;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('scope', ConnectorCredentialScope::Team->value)
            ->orWhere('created_by', $user->id));
    }

    /**
     * Marks this credential as the default for its (workspace, group column,
     * scope[, creator for a personal credential]) group, unsetting any
     * sibling default in the same group first, at most one default per
     * group at a time.
     */
    public function markAsDefault(): void
    {
        DB::transaction(function () {
            $this->defaultGroup()
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }

    /**
     * Whether another credential in this one's group is already the default.
     */
    public function groupHasDefault(): bool
    {
        return $this->defaultGroup()->where('id', '!=', $this->id)->where('is_default', true)->exists();
    }

    /**
     * @return Builder<static>
     */
    private function defaultGroup(): Builder
    {
        $column = $this->defaultGroupColumn();

        return static::query()
            ->where('workspace_id', $this->workspace_id)
            ->where($column, $this->{$column})
            ->where('scope', $this->scope->value)
            ->when(
                $this->scope === ConnectorCredentialScope::Personal,
                fn ($query) => $query->where('created_by', $this->created_by),
            );
    }
}
