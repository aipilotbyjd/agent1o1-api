<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents\Concerns;

use App\Models\Agents\Skill;

/**
 * One-way imports stay read-only; two-way sources allow local changes.
 */
trait GuardsSyncedSkills
{
    protected function ensureNotSynced(Skill $skill): void
    {
        abort_if($skill->isSynced() && ! $skill->source?->two_way, 422, 'This skill is synced from GitHub. Change it in the repository, or disconnect the repository to edit it here.');
    }
}
