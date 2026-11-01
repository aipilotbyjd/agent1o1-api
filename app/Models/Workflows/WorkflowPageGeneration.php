<?php

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to have AI design or rework a page, and its result. Runs on the
 * queue (`GeneratePageJob`); the content it produces is only a proposal:
 * the builder shows it, and applying it is the member's call.
 */
#[Fillable(['workflow_page_id', 'user_id', 'prompt', 'variant', 'keep_theme', 'status', 'content', 'error'])]
class WorkflowPageGeneration extends Model
{
    use HasUuids;

    public const string Pending = 'pending';

    public const string Running = 'running';

    public const string Completed = 'completed';

    public const string Failed = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'keep_theme' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WorkflowPage::class, 'workflow_page_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::Completed, self::Failed], true);
    }
}
