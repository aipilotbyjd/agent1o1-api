<?php

namespace App\Models\Broadcasts;

use App\Enums\Broadcasts\BroadcastRecipientStatus;
use App\Models\User;
use Database\Factories\Broadcasts\BroadcastRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'user_id', 'email', 'status', 'attempts', 'sent_at', 'error'])]
class BroadcastRecipient extends Model
{
    /** @use HasFactory<BroadcastRecipientFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BroadcastRecipientStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BroadcastCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BroadcastCampaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
