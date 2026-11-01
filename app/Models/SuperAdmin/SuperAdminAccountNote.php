<?php

namespace App\Models\SuperAdmin;

use Database\Factories\SuperAdmin\SuperAdminAccountNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note one admin left for the others on a user or a workspace.
 */
#[Fillable(['subject_type', 'subject_id', 'super_admin_id', 'body'])]
class SuperAdminAccountNote extends Model
{
    /** @use HasFactory<SuperAdminAccountNoteFactory> */
    use HasFactory, HasUuids;

    public function author(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }
}
