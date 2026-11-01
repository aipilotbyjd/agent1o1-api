<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone opening a workflow page (`view`) or starting to fill it in
 * (`start`). At most one of each per visitor, variant and day (see
 * `PageInsights::record()`) so counting rows counts unique visitors.
 * `visitor_hash` is a keyed hash; it never holds an address or id as-is.
 */
#[Fillable(['workflow_page_id', 'variant', 'type', 'visitor_hash', 'day', 'created_at'])]
class WorkflowPageEvent extends Model
{
    public const string View = 'view';

    public const string Start = 'start';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WorkflowPage::class, 'workflow_page_id');
    }
}
