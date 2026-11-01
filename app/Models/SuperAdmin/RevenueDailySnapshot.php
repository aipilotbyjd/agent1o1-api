<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Subscribers and MRR for one plan on one day. Written by `revenue:snapshot`.
 */
#[Fillable(['date', 'plan_slug', 'currency', 'subscribers', 'mrr'])]
class RevenueDailySnapshot extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'subscribers' => 'integer',
            'mrr' => 'integer',
        ];
    }
}
