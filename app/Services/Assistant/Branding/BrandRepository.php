<?php

namespace App\Services\Assistant\Branding;

use App\Models\Workspaces\Workspace;

/**
 * Where the assistant's brand comes from. Bound to `ConfigBrandRepository`
 * today; a database-backed implementation (global rename from an admin
 * screen, per-workspace white-label) can replace the binding without any
 * consumer changing, which is why `$workspace` is accepted even though the
 * config implementation ignores it.
 */
interface BrandRepository
{
    public function current(?Workspace $workspace = null): Brand;
}
