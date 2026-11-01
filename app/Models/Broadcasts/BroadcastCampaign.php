<?php

namespace App\Models\Broadcasts;

use App\Enums\Broadcasts\BroadcastCategory;
use App\Enums\Broadcasts\BroadcastStatus;
use App\Models\SuperAdmin\SuperAdmin;
use Database\Factories\Broadcasts\BroadcastCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An email a super admin sends to a segment. Counters are kept on the row and
 * recomputed from the recipient rows (`BroadcastCampaignService::refreshProgress`),
 * which are the source of truth.
 */
#[Fillable([
    'name', 'category', 'subject', 'body_markdown', 'audience', 'status', 'scheduled_at',
    'started_at', 'finished_at', 'recipient_count', 'sent_count', 'failed_count',
    'skipped_count', 'failure_reason', 'created_by',
])]
class BroadcastCampaign extends Model
{
    /** @use HasFactory<BroadcastCampaignFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => 'marketing',
        'status' => 'draft',
        'recipient_count' => 0,
        'sent_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => BroadcastCategory::class,
            'status' => BroadcastStatus::class,
            'audience' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SuperAdmin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by');
    }

    /**
     * @return HasMany<BroadcastRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class, 'campaign_id');
    }
}
