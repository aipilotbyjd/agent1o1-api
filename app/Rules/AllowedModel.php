<?php

namespace App\Rules;

use App\Models\Workspaces\Workspace;
use App\Services\Ai\WorkspaceModels;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A model the workspace lets its members pick: see
 * `WorkspaceModelSettings::allowed_model_catalog_ids`. Takes a catalog id,
 * or with `bySlug` a catalog slug (a value that matches no catalog entry,
 * such as a raw model id, isn't the allow-list's business).
 */
class AllowedModel implements ValidationRule
{
    public function __construct(
        private readonly Workspace|string|null $workspace,
        private readonly bool $bySlug = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || $this->workspace === null) {
            return;
        }

        $workspaceId = $this->workspace instanceof Workspace ? $this->workspace->id : $this->workspace;
        $models = app(WorkspaceModels::class);
        $allowed = $this->bySlug ? $models->allowsSlug($workspaceId, $value) : $models->allows($workspaceId, $value);

        if (! $allowed) {
            $fail('This model isn\'t allowed in this workspace. Ask an admin, or pick another model.');
        }
    }
}
