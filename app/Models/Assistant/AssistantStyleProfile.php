<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantStyleKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The owner's Tone or Design preferences as plain-language notes, put in
 * front of the model on every turn. Changed only through `StyleProfiles`,
 * which keeps a revision of every version.
 */
#[Fillable(['assistant_id', 'kind', 'body', 'version'])]
class AssistantStyleProfile extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AssistantStyleKind::class,
            'version' => 'integer',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AssistantStyleRevision::class);
    }
}
