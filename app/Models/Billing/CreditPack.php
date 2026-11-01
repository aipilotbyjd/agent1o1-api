<?php

namespace App\Models\Billing;

use App\Enums\Billing\CreditPackStatus;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Billing\CreditPackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'purchased_by',
    'pack_key',
    'credits_amount',
    'price_cents',
    'currency',
    'stripe_checkout_session_id',
    'stripe_payment_intent_id',
    'status',
    'purchased_at',
])]
class CreditPack extends Model
{
    /** @use HasFactory<CreditPackFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => CreditPackStatus::Pending,
        'currency' => 'usd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CreditPackStatus::class,
            'credits_amount' => 'integer',
            'price_cents' => 'integer',
            'purchased_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }
}
