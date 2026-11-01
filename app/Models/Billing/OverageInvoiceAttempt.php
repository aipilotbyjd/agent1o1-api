<?php

namespace App\Models\Billing;

use App\Enums\Billing\OverageInvoiceAttemptStatus;
use App\Models\Workspaces\Workspace;
use Database\Factories\Billing\OverageInvoiceAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A durable record of one overage invoice attempt: which credits of which
 * usage periods it claimed, and whether Stripe took it. See
 * `BillOverageCreditsAction`.
 */
#[Fillable(['workspace_id', 'status', 'credits', 'amount_cents', 'allocations', 'stripe_invoice_id'])]
class OverageInvoiceAttempt extends Model
{
    /** @use HasFactory<OverageInvoiceAttemptFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OverageInvoiceAttemptStatus::class,
            'credits' => 'integer',
            'amount_cents' => 'integer',
            'allocations' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
