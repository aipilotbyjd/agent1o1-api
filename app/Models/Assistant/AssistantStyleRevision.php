<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantStyleSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assistant_style_profile_id', 'version', 'body', 'source', 'reason'])]
class AssistantStyleRevision extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AssistantStyleSource::class,
            'version' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AssistantStyleProfile::class, 'assistant_style_profile_id');
    }
}
