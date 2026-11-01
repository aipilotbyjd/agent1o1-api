<?php

namespace App\Models\Billing;

use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One readable before/after entry in the pricing catalog's history.
 * `subject_type` is `plan`, `pack`, `interval` or `coupon`.
 */
#[Fillable(['plan_id', 'subject_type', 'subject_key', 'super_admin_id', 'action', 'before', 'after', 'reason'])]
class PricingChangeLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SuperAdmin, $this>
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
