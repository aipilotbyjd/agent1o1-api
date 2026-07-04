<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\WorkspaceMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['id', 'workspace_id', 'user_id', 'role', 'joined_at'])]
class WorkspaceMember extends Pivot
{
    /** @use HasFactory<WorkspaceMemberFactory> */
    use HasFactory, HasUuids;

    protected $table = 'workspace_members';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'joined_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
