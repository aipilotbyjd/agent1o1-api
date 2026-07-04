<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'workspace_id',
    'created_by',
    'type',
    'provider',
    'model',
    'prompt_summary',
    'tokens_used',
])]
class AiGenerationLog extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
        ];
    }
}
