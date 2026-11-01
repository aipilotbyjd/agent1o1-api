<?php

namespace App\Models\Nodes;

use Database\Factories\Nodes\CatalogVendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Who makes an integration. A vendor with two or more apps is offered as a
 * filter in the editor's Apps panel.
 */
#[Fillable(['slug', 'label', 'sort_order'])]
class CatalogVendor extends Model
{
    /** @use HasFactory<CatalogVendorFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
