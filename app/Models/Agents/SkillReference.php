<?php

namespace App\Models\Agents;

use Database\Factories\Agents\SkillReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['skill_id', 'title', 'content', 'sort_order'])]
class SkillReference extends Model
{
    /** @use HasFactory<SkillReferenceFactory> */
    use HasFactory, HasUuids;

    public const int CONTENT_MAX_LENGTH = 100000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
