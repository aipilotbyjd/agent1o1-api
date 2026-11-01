<?php

namespace App\Models\Billing;

use App\Enums\Billing\ClaimDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One decision on whether a workspace's owner may receive the free
 * allowance. Written once per workspace by `FreeCreditPolicy`; later periods
 * reuse it.
 */
#[Fillable(['user_id', 'workspace_id', 'email_hash', 'oauth_hash', 'network_hash', 'decision', 'reason'])]
class FreeCreditClaim extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ClaimDecision::class,
        ];
    }
}
