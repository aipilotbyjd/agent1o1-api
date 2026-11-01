<?php

namespace App\Models\Nodes;

use Database\Factories\Nodes\CatalogFunctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What an integration is used for: a section of the editor's Apps panel. An
 * app lists one to three by slug. The `other` row is the system's own and
 * cannot be renamed away or removed.
 */
#[Fillable(['slug', 'label', 'sort_order', 'is_system'])]
class CatalogFunction extends Model
{
    /** @use HasFactory<CatalogFunctionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_system' => 'boolean'];
    }
}
