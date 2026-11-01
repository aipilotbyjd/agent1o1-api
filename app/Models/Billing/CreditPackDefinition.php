<?php

namespace App\Models\Billing;

use Database\Factories\Billing\CreditPackDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A credit pack on sale, as edited by a super admin. Read through
 * `PackCatalog`, which falls back to `config('billing.packs')`.
 */
#[Fillable(['key', 'label', 'credits', 'price_cents', 'badge', 'is_active', 'sort_order'])]
class CreditPackDefinition extends Model
{
    /** @use HasFactory<CreditPackDefinitionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'price_cents' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
