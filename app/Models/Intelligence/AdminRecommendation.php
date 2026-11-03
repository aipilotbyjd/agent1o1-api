<?php

namespace App\Models\Intelligence;

use App\Enums\Intelligence\RecommendationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An action the intelligence proposes, with the evidence for it. Raised by
 * `RecommendationEngine`; applied or dismissed by an operator.
 */
#[Fillable(['rule', 'fingerprint', 'severity', 'subject_type', 'subject_id', 'title', 'body', 'evidence', 'action', 'status', 'decided_by', 'decided_at', 'decision_note', 'last_seen_at'])]
class AdminRecommendation extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'action' => 'array',
            'status' => RecommendationStatus::class,
            'decided_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
