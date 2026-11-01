<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantToolRule as Rule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The owner's standing choice for one tool: always allow, always ask, or
 * never offer it. Tools without a rule follow their effect's default.
 */
#[Fillable(['assistant_id', 'tool', 'rule'])]
class AssistantToolRule extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rule' => Rule::class,
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
