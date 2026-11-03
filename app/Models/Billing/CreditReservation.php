<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Credits held for one piece of work in flight. See the migration, and
 * `CreditReservations`, which is the only writer.
 */
#[Fillable(['workspace_id', 'subject_type', 'subject_id', 'credits', 'expires_at', 'released_at'])]
class CreditReservation extends Model
{
    use HasUuids;

    /**
     * Holds still counting against the balance.
     *
     * @param  Builder<CreditReservation>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereNull('released_at')->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
