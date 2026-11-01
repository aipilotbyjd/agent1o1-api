<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\SubscriptionStatus;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registration made with a provider so it tells us when something changes:
 * a Gmail watch, an Outlook subscription, a Drive channel, a GitHub hook.
 *
 * Shared. Every trigger watching the same thing (`resource_key`) through the
 * same connected account uses one watch, so provider calls and quotas grow
 * with what is watched, not with the number of triggers. The watch is renewed
 * before it expires and removed, after a grace period, once no trigger uses it.
 *
 * @see docs/APP_TRIGGERS_PLAN.md "Share everything that can be shared"
 */
#[Fillable([
    'workspace_id', 'credential_id', 'provider', 'resource_key', 'external_id', 'secret',
    'meta', 'status', 'expires_at', 'renew_at', 'release_at', 'last_ok_at', 'last_error',
])]
#[Hidden(['secret'])]
class ProviderWatch extends Model
{
    use HasUuids;

    /**
     * Defaults for a new watch.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * The attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'meta' => 'array',
            'status' => SubscriptionStatus::class,
            'expires_at' => 'datetime',
            'renew_at' => 'datetime',
            'release_at' => 'datetime',
            'last_ok_at' => 'datetime',
        ];
    }

    /**
     * The workspace the watch belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The connected account the watch was made with.
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(ConnectorCredential::class, 'credential_id');
    }

    /**
     * The trigger subscriptions that use this watch.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TriggerSubscription::class, 'watch_id');
    }
}
