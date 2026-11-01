<?php

namespace App\Models\Billing;

use App\Enums\Billing\ClaimDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One trial a person or card has used, or was refused. Written by
 * `TrialCreditPolicy` when checkout decides on a trial and again when
 * Stripe reports the card the trial started with.
 */
#[Fillable(['user_id', 'workspace_id', 'subscription_id', 'email_hash', 'oauth_hash', 'card_fingerprint', 'card_funding', 'decision', 'reason'])]
class TrialClaim extends Model
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
