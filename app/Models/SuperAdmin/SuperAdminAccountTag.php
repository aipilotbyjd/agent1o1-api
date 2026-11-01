<?php

namespace App\Models\SuperAdmin;

use Database\Factories\SuperAdmin\SuperAdminAccountTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A short label on a user or a workspace ("vip", "watch", "do-not-refund").
 */
#[Fillable(['subject_type', 'subject_id', 'tag', 'super_admin_id'])]
class SuperAdminAccountTag extends Model
{
    /** @use HasFactory<SuperAdminAccountTagFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;
}
