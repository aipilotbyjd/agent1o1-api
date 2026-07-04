<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'created_by',
    'key',
    'value',
    'is_secret',
])]
class Variable extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedValue(): mixed
    {
        return $this->is_secret ? decrypt($this->value) : $this->value;
    }
}
