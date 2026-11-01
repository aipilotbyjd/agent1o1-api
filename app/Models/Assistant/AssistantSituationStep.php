<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantSituationStepStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assistant_situation_id', 'position', 'body', 'status'])]
class AssistantSituationStep extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'todo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssistantSituationStepStatus::class,
            'position' => 'integer',
        ];
    }

    public function situation(): BelongsTo
    {
        return $this->belongsTo(AssistantSituation::class, 'assistant_situation_id');
    }
}
