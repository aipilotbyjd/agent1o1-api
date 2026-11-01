<?php

namespace App\Models\Nodes;

use Database\Factories\Nodes\CatalogNodeOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A flag an admin put on one built-in node: `beta`, `deprecated`, or `hidden`
 * from the library. Hidden nodes still resolve for workflows that already use them.
 */
#[Fillable(['node_type', 'status', 'note'])]
class CatalogNodeOverride extends Model
{
    /** @use HasFactory<CatalogNodeOverrideFactory> */
    use HasFactory, HasUuids;

    public const STATUSES = ['beta', 'deprecated', 'hidden'];
}
