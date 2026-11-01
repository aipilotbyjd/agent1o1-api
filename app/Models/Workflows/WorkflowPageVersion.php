<?php

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved copy of a page's draft (version B's too, when it has one) to
 * look back at or restore. Taken on publish (`published`), on request
 * (`saved`), and automatically before a restore or an AI rewrite replaces
 * the draft, so neither can lose work. See `PageVersions`.
 */
#[Fillable(['workflow_page_id', 'number', 'kind', 'label', 'content', 'variant_content', 'created_by', 'created_at'])]
class WorkflowPageVersion extends Model
{
    use HasUuids;

    public const string Published = 'published';

    public const string Saved = 'saved';

    public const string BeforeRestore = 'before_restore';

    public const string BeforeGeneration = 'before_generation';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'variant_content' => 'array',
            'number' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WorkflowPage::class, 'workflow_page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
