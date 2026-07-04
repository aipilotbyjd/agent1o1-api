<?php

namespace App\Authorization;

use App\Enums\Feature;
use App\Enums\Permission;

final class FeatureGates
{
    /**
     * Permissions that additionally require a plan feature to be enabled.
     * A permission absent from this map is role-only — no plan check.
     *
     * Add an entry here when a new Permission case requires a specific Feature
     * flag on the workspace's active plan (e.g. Permission::AiGenerate => Feature::AiGeneration).
     *
     * @return array<string, Feature>
     */
    public static function map(): array
    {
        return [
            // Phase 3/4 workspace permissions are role-only — no plan feature gates yet.
            // Populate this map when product-feature permissions (workflows, AI, API access)
            // are added in later phases.
        ];
    }
}
