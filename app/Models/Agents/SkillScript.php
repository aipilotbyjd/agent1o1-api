<?php

namespace App\Models\Agents;

use Database\Factories\Agents\SkillScriptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['skill_id', 'name', 'description', 'language', 'code', 'is_enabled'])]
class SkillScript extends Model
{
    /** @use HasFactory<SkillScriptFactory> */
    use HasFactory, HasUuids;

    /**
     * The languages a script may be written in.
     *
     * @var list<string>
     */
    public const LANGUAGES = ['python', 'javascript', 'typescript', 'bash'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
