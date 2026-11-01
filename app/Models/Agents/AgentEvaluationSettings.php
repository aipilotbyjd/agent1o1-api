<?php

namespace App\Models\Agents;

use Database\Factories\Agents\AgentEvaluationSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether/how one `Agent`'s live `AgentSession`s are automatically graded
 * after each turn: see `Services\Agents\SessionEvaluator` and the
 * migration's docblock.
 */
#[Fillable([
    'agent_id', 'is_enabled', 'model', 'sentiment_enabled', 'sentiment_affects_grade',
    'sentiment_guidance', 'suggest_tags_automatically', 'criteria', 'tags', 'data_points',
])]
class AgentEvaluationSettings extends Model
{
    /** @use HasFactory<AgentEvaluationSettingsFactory> */
    use HasFactory, HasUuids;

    /**
     * Gumloop's published limits (docs/gumloop/output/raw/core-concepts/evaluations.md's
     * "Limits" table): kept as the ceiling here too so a config can't grow
     * to a size the judge prompt can no longer reason about accurately.
     */
    public const int MAX_CRITERIA = 30;

    public const int MAX_TAGS = 50;

    public const int MAX_DATA_POINTS = 40;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => false,
        'sentiment_enabled' => true,
        'sentiment_affects_grade' => false,
        'suggest_tags_automatically' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sentiment_enabled' => 'boolean',
            'sentiment_affects_grade' => 'boolean',
            'suggest_tags_automatically' => 'boolean',
            'criteria' => 'array',
            'tags' => 'array',
            'data_points' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    public function notifyCriteria(): array
    {
        return array_values(array_filter(
            $this->criteria ?? [],
            fn (array $criterion): bool => ($criterion['priority'] ?? null) === 'notify',
        ));
    }
}
