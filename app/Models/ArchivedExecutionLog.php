<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'execution_id',
    'workspace_id',
    'node_id',
    'level',
    'message',
    'context',
    'logged_at',
    'archived_at',
])]
class ArchivedExecutionLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'logged_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
