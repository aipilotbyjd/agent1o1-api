<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\SubscriptionStatus;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The link between a trigger and the provider's push events for it.
 *
 * One per trigger. For a platform-wide app such as Slack's Events API there is
 * nothing to register with the provider per trigger, so a subscription is just
 * the routing record: events for `routing_key` go to this trigger. Providers
 * that need a hook created on the customer's resource will use `external_id`,
 * `cursor` and `expires_at` for it.
 */
#[Fillable([
    'workspace_id', 'trigger_id', 'watch_id', 'provider', 'mechanism', 'routing_key', 'status',
    'external_id', 'cursor', 'expires_at', 'last_event_at', 'last_ok_at', 'last_error',
])]
class TriggerSubscription extends Model
{
    use HasUuids;

    /**
     * Defaults for a new subscription.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'mechanism' => 'push',
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
            'status' => SubscriptionStatus::class,
            'cursor' => 'array',
            'expires_at' => 'datetime',
            'last_event_at' => 'datetime',
            'last_ok_at' => 'datetime',
        ];
    }

    /**
     * The trigger events are routed to.
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class);
    }

    /**
     * The shared provider watch behind this subscription, if it uses one.
     */
    public function watch(): BelongsTo
    {
        return $this->belongsTo(ProviderWatch::class, 'watch_id');
    }

    /**
     * The workspace the subscription belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
