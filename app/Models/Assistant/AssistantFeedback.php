<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantFeedbackRating;
use App\Enums\Assistant\AssistantFeedbackStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The owner's thumbs up/down (and optional comment) on one reply. A comment
 * may teach the assistant a lasting preference (`StyleLearner`).
 */
#[Fillable(['assistant_id', 'assistant_message_id', 'rating', 'comment', 'status', 'applied_change'])]
class AssistantFeedback extends Model
{
    use HasUuids;

    protected $table = 'assistant_feedback';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => AssistantFeedbackRating::class,
            'status' => AssistantFeedbackStatus::class,
            'applied_change' => 'array',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'assistant_message_id');
    }
}
